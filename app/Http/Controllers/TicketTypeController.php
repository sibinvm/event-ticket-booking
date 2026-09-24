<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketTypeRequest;
use App\Http\Requests\UpdateTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TicketTypeController extends Controller
{
    /**
     * Display a listing of ticket types for the specified event.
     */
    public function index(Event $event): JsonResponse
    {
        Gate::authorize('viewAny', [TicketType::class, $event]);

        return TicketTypeResource::collection($event->ticketTypes)->response();
    }

    /**
     * Store a newly created ticket type for the specified event.
     */
    public function store(StoreTicketTypeRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('create', [TicketType::class, $event]);

        $ticketType = $event->ticketTypes()->create($request->validated());

        return (new TicketTypeResource($ticketType))->response()->setStatusCode(201);
    }

    /**
     * Display the specified ticket type.
     */
    public function show(Event $event, TicketType $ticketType): JsonResponse
    {
        $this->ensureTicketTypeBelongsToEvent($event, $ticketType);

        Gate::authorize('view', [$ticketType, $event]);

        return (new TicketTypeResource($ticketType))->response();
    }

    /**
     * Update the specified ticket type.
     */
    public function update(UpdateTicketTypeRequest $request, Event $event, TicketType $ticketType): JsonResponse
    {
        $this->ensureTicketTypeBelongsToEvent($event, $ticketType);

        Gate::authorize('update', [$ticketType, $event]);

        $ticketType->update($request->validated());

        return (new TicketTypeResource($ticketType))->response();
    }

    /**
     * Remove the specified ticket type from storage.
     */
    public function destroy(Event $event, TicketType $ticketType): JsonResponse
    {
        $this->ensureTicketTypeBelongsToEvent($event, $ticketType);

        Gate::authorize('delete', [$ticketType, $event]);

        $ticketType->delete();

        return response()->json([
            'message' => 'Ticket type deleted successfully',
        ], 200);
    }

    /**
     * Ensure the ticket type belongs to the event specified in the URL.
     */
    protected function ensureTicketTypeBelongsToEvent(Event $event, TicketType $ticketType): void
    {
        if ($ticketType->event_id !== $event->id) {
            abort(404, 'The ticket type does not belong to the specified event.');
        }
    }
}
