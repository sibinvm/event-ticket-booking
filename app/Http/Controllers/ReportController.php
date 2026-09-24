<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Display a summary report of all confirmed bookings.
     */
    public function summary(Request $request): JsonResponse
    {
        $totalBookings = 0;
        $totalTicketsSold = 0;
        $totalRevenue = '0.00';

        Booking::query()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->select(['id', 'quantity', 'total_amount'])
            ->chunkById(500, function ($bookings) use (&$totalBookings, &$totalTicketsSold, &$totalRevenue): void {
                foreach ($bookings as $booking) {
                    $totalBookings++;
                    $totalTicketsSold += (int) $booking->quantity;
                    $totalRevenue = bcadd($totalRevenue, (string) $booking->total_amount, 2);
                }
            });

        return response()->json([
            'total_bookings' => $totalBookings,
            'total_tickets_sold' => $totalTicketsSold,
            'total_revenue' => $totalRevenue,
        ]);
    }

    /**
     * Display a report for a specific event.
     */
    public function eventReport(Request $request, Event $event): JsonResponse
    {
        // Only the event owner can access the report
        if ($event->organizer_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized. You can only access reports for your own events.',
            ], 403);
        }

        $totalBookings = 0;
        $totalTicketsSold = 0;
        $totalRevenue = '0.00';

        $event->bookings()
            ->where('status', Booking::STATUS_CONFIRMED)
            ->select(['id', 'event_id', 'quantity', 'total_amount'])
            ->chunkById(500, function ($bookings) use (&$totalBookings, &$totalTicketsSold, &$totalRevenue): void {
                foreach ($bookings as $booking) {
                    $totalBookings++;
                    $totalTicketsSold += (int) $booking->quantity;
                    $totalRevenue = bcadd($totalRevenue, (string) $booking->total_amount, 2);
                }
            });

        return response()->json([
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'venue' => $event->venue,
                'starts_at' => $event->starts_at,
                'status' => $event->status,
            ],
            'total_bookings' => $totalBookings,
            'total_tickets_sold' => $totalTicketsSold,
            'total_revenue' => $totalRevenue,
        ]);
    }
}
