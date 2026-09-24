<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'event' => [
                'id' => $this->event_id,
                'title' => $this->event?->title,
                'venue' => $this->event?->venue,
                'starts_at' => $this->event?->starts_at,
            ],
            'ticket_type' => [
                'id' => $this->ticket_type_id,
                'name' => $this->ticketType?->name,
                'price' => $this->ticketType?->price,
            ],
            'quantity' => $this->quantity,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
