<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateEventSalesStats
{
    /**
     * Handle the event.
     */
    public function handle(BookingConfirmed $event): void
    {
        $eventId = $event->booking->event_id;

        $confirmedBookings = Booking::where('event_id', $eventId)
            ->where('status', Booking::STATUS_CONFIRMED);

        $totalBookings = (clone $confirmedBookings)->count();
        $totalTicketsSold = (int) (clone $confirmedBookings)->sum('quantity');
        $totalRevenue = (clone $confirmedBookings)->sum('total_amount');

        $stats = [
            'event_id' => $eventId,
            'total_confirmed_bookings' => $totalBookings,
            'total_tickets_sold' => $totalTicketsSold,
            'total_revenue' => number_format((float) $totalRevenue, 2, '.', ''),
            'last_updated' => now()->toIso8601String(),
        ];

        Cache::put("events.{$eventId}.sales_stats", $stats, now()->addDays(7));

        Log::info("Event sales statistics updated for Event #{$eventId}", $stats);
    }
}
