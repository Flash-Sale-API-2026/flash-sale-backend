<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PublicEventViewService
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->summaryQuery()
            ->orderBy('start_sales_at')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $eventId): Event
    {
        return $this->summaryQuery()
            ->whereKey($eventId)
            ->firstOrFail();
    }

    /**
     * @return Builder<Event>
     */
    private function summaryQuery(): Builder
    {
        $now = now();

        return Event::query()->withCount([
            'tickets as available_tickets_count' => function (Builder $query) use ($now): void {
                $query->where(function (Builder $query) use ($now): void {
                    $query
                        ->where('status', Ticket::STATUS_AVAILABLE)
                        ->orWhere(function (Builder $query) use ($now): void {
                            $query
                                ->where('status', Ticket::STATUS_RESERVED)
                                ->where('reserved_until', '<=', $now);
                        });
                });
            },
            'tickets as reserved_tickets_count' => function (Builder $query) use ($now): void {
                $query
                    ->where('status', Ticket::STATUS_RESERVED)
                    ->where('reserved_until', '>', $now);
            },
            'tickets as sold_tickets_count' => function (Builder $query): void {
                $query->where('status', Ticket::STATUS_SOLD);
            },
        ]);
    }
}
