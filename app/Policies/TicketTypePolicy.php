<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;

class TicketTypePolicy
{
    /**
     * Determine whether the user can view any ticket types for the event.
     */
    public function viewAny(User $user, Event $event): bool
    {
        return $user->isOrganizer() && $event->organizer_id === $user->id;
    }

    /**
     * Determine whether the user can view the ticket type.
     */
    public function view(User $user, TicketType $ticketType, ?Event $event = null): bool
    {
        $event ??= $ticketType->event;

        return $user->isOrganizer()
            && $event !== null
            && $event->organizer_id === $user->id
            && $ticketType->event_id === $event->id;
    }

    /**
     * Determine whether the user can create ticket types for the event.
     */
    public function create(User $user, Event $event): bool
    {
        return $user->isOrganizer() && $event->organizer_id === $user->id;
    }

    /**
     * Determine whether the user can update the ticket type.
     */
    public function update(User $user, TicketType $ticketType, ?Event $event = null): bool
    {
        $event ??= $ticketType->event;

        return $user->isOrganizer()
            && $event !== null
            && $event->organizer_id === $user->id
            && $ticketType->event_id === $event->id;
    }

    /**
     * Determine whether the user can delete the ticket type.
     */
    public function delete(User $user, TicketType $ticketType, ?Event $event = null): bool
    {
        $event ??= $ticketType->event;

        return $user->isOrganizer()
            && $event !== null
            && $event->organizer_id === $user->id
            && $ticketType->event_id === $event->id;
    }
}
