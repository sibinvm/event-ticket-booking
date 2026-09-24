<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class NotifyOrganizerWhenSoldOut implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Determine whether the listener should be queued.
     * Only queues when the booked ticket type reaches 0 available tickets.
     */
    public function shouldQueue(BookingConfirmed $event): bool
    {
        $ticketType = $event->booking->ticketType()->first();

        return $ticketType !== null && (int) $ticketType->quantity === 0;
    }

    /**
     * Handle the event.
     */
    public function handle(BookingConfirmed $event): void
    {
        $ticketType = $event->booking->ticketType()->first();

        if (! $ticketType || (int) $ticketType->quantity > 0) {
            return;
        }

        $eventModel = $event->booking->event()->with('organizer')->first();
        $organizer = $eventModel?->organizer;

        Log::warning("Ticket type sold out for Event #{$eventModel?->id}", [
            'event_id' => $eventModel?->id,
            'event_title' => $eventModel?->title,
            'ticket_type_id' => $ticketType->id,
            'ticket_type_name' => $ticketType->name,
            'organizer_id' => $organizer?->id,
            'organizer_email' => $organizer?->email,
            'message' => "Ticket type '{$ticketType->name}' is now SOLD OUT for event '{$eventModel?->title}'.",
        ]);
    }
}
