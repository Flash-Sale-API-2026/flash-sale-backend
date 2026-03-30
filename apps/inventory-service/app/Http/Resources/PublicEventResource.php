<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicEventResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'total_tickets' => $this->total_tickets,
            'available_tickets' => (int) ($this->available_tickets_count ?? 0),
            'reserved_tickets' => (int) ($this->reserved_tickets_count ?? 0),
            'sold_tickets' => (int) ($this->sold_tickets_count ?? 0),
            'start_sales_at' => $this->start_sales_at?->toIso8601String(),
            'sales_started' => $this->start_sales_at !== null && $this->start_sales_at->lte(now()),
        ];
    }
}
