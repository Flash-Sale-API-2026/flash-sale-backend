<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfirmedReservationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_id' => (int) data_get($this->resource, 'ticket_id'),
            'event_id' => (int) data_get($this->resource, 'event_id'),
            'user_id' => (int) data_get($this->resource, 'user_id'),
            'amount' => (string) data_get($this->resource, 'amount'),
            'reserved_until' => (string) data_get($this->resource, 'reserved_until'),
        ];
    }
}
