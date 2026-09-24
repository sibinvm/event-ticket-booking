<?php

namespace App\Http\Controllers;

use App\Events\BookingConfirmed;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Book tickets for an event.
     *
     * Handles ticket booking with pessimistic row-locking (lockForUpdate)
     * inside a database transaction to prevent overselling and race conditions.
     */
    public function store(StoreBookingRequest $request, Event $event): JsonResponse
    {
        $user = $request->user();

        // 1. Role validation: only attendees can book tickets
        if (! $user->isAttendee()) {
            return response()->json([
                'message' => 'Unauthorized. Only attendees can book tickets.',
            ], 403);
        }

        // 2. Event status and date checks
        if (! $event->isPublished()) {
            return response()->json([
                'message' => 'Cannot book tickets for an unpublished event.',
            ], 422);
        }

        if ($event->starts_at <= now()) {
            return response()->json([
                'message' => 'Cannot book tickets for an event that has already started.',
            ], 422);
        }

        $validated = $request->validated();
        $ticketTypeId = (int) $validated['ticket_type_id'];
        $requestedQuantity = (int) $validated['quantity'];

        /**
         * Concurrency and Oversold Prevention:
         *
         * A database transaction combined with pessimistic locking (lockForUpdate)
         * guarantees atomic execution. Without lockForUpdate, two concurrent requests
         * could read the same available quantity simultaneously and both proceed,
         * resulting in an oversold state and negative inventory.
         *
         * lockForUpdate places an exclusive lock on the ticket_types row until the transaction
         * completes, forcing competing requests to wait and re-read the updated quantity.
         */
        $booking = DB::transaction(function () use ($user, $event, $ticketTypeId, $requestedQuantity) {
            // Step 1: Lock the ticket type row using lockForUpdate()
            $ticketType = TicketType::where('id', $ticketTypeId)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            // Step 2: Re-check that the ticket type belongs to the event
            if (! $ticketType) {
                throw new HttpResponseException(response()->json([
                    'message' => 'The selected ticket type does not belong to this event.',
                ], 422));
            }

            // Step 3: Re-check the event is published and upcoming inside the lock
            if (! $event->isPublished()) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Cannot book tickets for an unpublished event.',
                ], 422));
            }

            if ($event->starts_at <= now()) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Cannot book tickets for an event that has already started.',
                ], 422));
            }

            // Step 4: Check available quantity after acquiring the exclusive lock
            if ($ticketType->quantity < $requestedQuantity) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Not enough tickets available for this ticket type.',
                ], 409));
            }

            // Step 5: Check user's maximum 5-ticket-per-event limit across all confirmed bookings
            $existingQuantity = (int) Booking::where('user_id', $user->id)
                ->where('event_id', $event->id)
                ->where('status', Booking::STATUS_CONFIRMED)
                ->lockForUpdate()
                ->sum('quantity');

            if (($existingQuantity + $requestedQuantity) > 5) {
                throw new HttpResponseException(response()->json([
                    'message' => "You cannot purchase more than 5 tickets per event. You currently have {$existingQuantity} confirmed ticket(s).",
                ], 422));
            }

            // Step 6: Calculate total_amount on the server from the database price
            $totalAmount = bcmul((string) $ticketType->price, (string) $requestedQuantity, 2);

            // Step 7: Create the booking record with confirmed status
            $booking = Booking::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'quantity' => $requestedQuantity,
                'total_amount' => $totalAmount,
                'status' => Booking::STATUS_CONFIRMED,
            ]);

            // Step 8: Decrease the available ticket quantity atomically
            $ticketType->decrement('quantity', $requestedQuantity);

            return $booking;
        });

        // Dispatch BookingConfirmed only AFTER the booking transaction succeeds
        BookingConfirmed::dispatch($booking);

        $booking->load(['event', 'ticketType']);

        return (new BookingResource($booking))->response()->setStatusCode(201);
    }

    /**
     * Cancel an existing booking.
     *
     * Validates attendee ownership, confirmed status, and 24-hour cutoff
     * before atomically cancelling the booking and restoring ticket inventory
     * with pessimistic row locking inside a database transaction.
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();

        // 1. Authorization: Only the attendee who owns the booking can cancel it
        if (! $user->isAttendee() || $booking->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. You can only cancel your own bookings.',
            ], 403);
        }

        /**
         * Concurrency and Seat Restoration:
         *
         * DB::transaction combined with lockForUpdate guarantees that:
         * 1. Concurrent cancellation attempts for the same booking cannot execute twice.
         * 2. Concurrent booking and cancellation requests on the same ticket type
         *    are serialized, preventing race conditions on inventory count.
         * 3. Booking status update and ticket quantity restoration happen atomically;
         *    if any error occurs halfway through, all changes are rolled back.
         */
        $cancelledBooking = DB::transaction(function () use ($user, $booking) {
            // Step 1: Lock the booking row using lockForUpdate()
            $lockedBooking = Booking::where('id', $booking->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedBooking) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Booking not found.',
                ], 404));
            }

            // Step 2: Re-verify booking ownership inside transaction
            if ($lockedBooking->user_id !== $user->id || ! $user->isAttendee()) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Unauthorized. You can only cancel your own bookings.',
                ], 403));
            }

            // Step 3: Verify booking is confirmed
            if ($lockedBooking->status !== Booking::STATUS_CONFIRMED) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Only confirmed bookings can be cancelled.',
                ], 422));
            }

            // Step 4: Verify event starts more than 24 hours from now
            $event = $lockedBooking->event;

            if (! $event || $event->starts_at <= now()) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Cannot cancel booking for an event that has already started.',
                ], 422));
            }

            if ($event->starts_at <= now()->addHours(24)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Bookings can only be cancelled at least 24 hours before the event starts.',
                ], 422));
            }

            // Step 5: Lock the ticket type row using lockForUpdate()
            $ticketType = TicketType::where('id', $lockedBooking->ticket_type_id)
                ->lockForUpdate()
                ->first();

            if (! $ticketType) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Associated ticket type not found.',
                ], 422));
            }

            // Step 6: Change booking status to cancelled
            $lockedBooking->update([
                'status' => Booking::STATUS_CANCELLED,
            ]);

            // Step 7: Restore ticket quantity atomically inside the cancellation request
            $ticketType->increment('quantity', $lockedBooking->quantity);

            return $lockedBooking;
        });

        $cancelledBooking->load(['event', 'ticketType']);

        return (new BookingResource($cancelledBooking))->response();
    }
}
