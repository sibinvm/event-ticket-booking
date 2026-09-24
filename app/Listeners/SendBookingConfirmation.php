<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendBookingConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking->loadMissing(['user', 'event', 'ticketType']);

        Log::info("Booking confirmation sent for Booking #{$booking->id}", [
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'user_email' => $booking->user?->email,
            'event_id' => $booking->event_id,
            'event_title' => $booking->event?->title,
            'ticket_type' => $booking->ticketType?->name,
            'quantity' => $booking->quantity,
            'total_amount' => $booking->total_amount,
        ]);
    }
}
