<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListEventsRequest;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class EventController extends Controller
{
    /**
     * Display a listing of published upcoming events.
     */
    public function index(ListEventsRequest $request): JsonResponse
    {
        $events = Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->where('starts_at', '>', now())
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where('title', 'like', '%'.$request->query('search').'%');
            })
            ->when($request->filled('from'), function ($query) use ($request): void {
                $query->where('starts_at', '>=', $request->query('from'));
            })
            ->when($request->filled('to'), function ($query) use ($request): void {
                $to = $request->query('to');
                if (is_string($to) && strlen($to) === 10) {
                    $to = Carbon::parse($to)->endOfDay();
                }
                $query->where('starts_at', '<=', $to);
            })
            ->with('ticketTypes')
            ->orderBy('starts_at', 'asc')
            ->paginate(10);

        return EventResource::collection($events)->response();
    }

    /**
     * Store a newly created event in storage.
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        Gate::authorize('create', Event::class);

        $validated = $request->validated();

        $event = $request->user()->events()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'venue' => $validated['venue'],
            'starts_at' => $validated['starts_at'],
            'status' => Event::STATUS_DRAFT,
        ]);

        $event->load('organizer');

        return (new EventResource($event))->response()->setStatusCode(201);
    }

    /**
     * Display the specified event.
     */
    public function show(Event $event): JsonResponse
    {
        Gate::authorize('view', $event);

        $event->load(['organizer', 'ticketTypes']);

        return (new EventResource($event))->response();
    }

    /**
     * Update the specified event in storage.
     */
    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        $event->update($request->validated());

        $event->load(['organizer', 'ticketTypes']);

        return (new EventResource($event))->response();
    }

    /**
     * Remove the specified event from storage.
     */
    public function destroy(Event $event): JsonResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully',
        ], 200);
    }

    /**
     * Publish the specified event.
     */
    public function publish(Event $event): JsonResponse
    {
        Gate::authorize('publish', $event);

        $event->update([
            'status' => Event::STATUS_PUBLISHED,
        ]);

        $event->load(['organizer', 'ticketTypes']);

        return (new EventResource($event))->response();
    }

    /**
     * Unpublish the specified event.
     */
    public function unpublish(Event $event): JsonResponse
    {
        Gate::authorize('unpublish', $event);

        $event->update([
            'status' => Event::STATUS_DRAFT,
        ]);

        $event->load(['organizer', 'ticketTypes']);

        return (new EventResource($event))->response();
    }
}
