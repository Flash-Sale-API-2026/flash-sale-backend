<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class LoadTestEventSeeder
{
    public function create(
        int $ticketCount,
        string $name,
        int $salesStartedMinutesAgo = 5,
        string $seatPrefix = 'LT',
    ): Event {
        return DB::transaction(function () use ($ticketCount, $name, $salesStartedMinutesAgo, $seatPrefix): Event {
            $now = now();
            $batchSize = 1000;

            $event = Event::query()->create([
                'name' => $name,
                'total_tickets' => $ticketCount,
                'start_sales_at' => $now->copy()->subMinutes($salesStartedMinutesAgo),
            ]);

            $tickets = [];

            for ($index = 1; $index <= $ticketCount; $index++) {
                $tickets[] = [
                    'event_id' => $event->id,
                    'seat_number' => sprintf('%s-%06d', $seatPrefix, $index),
                    'price' => '149.99',
                    'status' => Ticket::STATUS_AVAILABLE,
                    'user_id' => null,
                    'reserved_until' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($tickets) === $batchSize) {
                    Ticket::query()->insert($tickets);
                    $tickets = [];
                }
            }

            if ($tickets !== []) {
                Ticket::query()->insert($tickets);
            }

            return $event->refresh();
        });
    }
}
