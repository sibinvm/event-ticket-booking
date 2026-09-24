<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'venue' => $this->venue,
            'starts_at' => $this->starts_at,
            'status' => $this->status,
            'organizer' => $this->whenLoaded('organizer', fn () => [
                'id' => $this->organizer->id,
                'name' => $this->organizer->name,
                'email' => $this->organizer->email,
            ], [
                'id' => $this->organizer_id,
            ]),
            'ticket_types' => $this->whenLoaded('ticketTypes'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
