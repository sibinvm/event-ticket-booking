<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine whether the user can view the event.
     */
    public function view(User $user, Event $event): bool
    {
        return $user->isOrganizer() && $event->organizer_id === $user->id;
    }

    /**
     * Determine whether the user can create events.
     */
    public function create(User $user): bool
    {
        return $user->isOrganizer();
    }

    /**
     * Determine whether the user can update the event.
     */
    public function update(User $user, Event $event): bool
    {
        return $user->isOrganizer() && $event->organizer_id === $user->id;
    }

    /**
     * Determine whether the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        return $user->isOrganizer() && $event->organizer_id === $user->id;
    }

    /**
     * Determine whether the user can publish the event.
     */
    public function publish(User $user, Event $event): bool
    {
        return $user->isOrganizer() && $event->organizer_id === $user->id;
    }

    /**
     * Determine whether the user can unpublish the event.
     */
    public function unpublish(User $user, Event $event): bool
    {
        return $user->isOrganizer() && $event->organizer_id === $user->id;
    }
}
