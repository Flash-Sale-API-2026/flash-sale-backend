<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutTicketResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_id' => $this->id,
            'event_id' => $this->event_id,
            'seat_number' => $this->seat_number,
            'price' => $this->price,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'reserved_until' => $this->reserved_until?->toIso8601String(),
        ];
    }
}
