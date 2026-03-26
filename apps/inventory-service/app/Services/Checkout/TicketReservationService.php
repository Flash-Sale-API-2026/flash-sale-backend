<?php

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class TicketReservationService
{
    /**
     * @return array{ticket: ?Ticket, created: bool}
     */
    public function reserve(Event $event, int $userId): array
    {
        return DB::transaction(function () use ($event, $userId): array {
            $now = now();

            $existingReservation = Ticket::query()
                ->where('event_id', $event->id)
                ->where('user_id', $userId)
                ->where(function ($query) use ($now): void {
                    $query
                        ->where('status', Ticket::STATUS_SOLD)
                        ->orWhere(function ($query) use ($now): void {
                            $query
                                ->where('status', Ticket::STATUS_RESERVED)
                                ->where('reserved_until', '>', $now);
                        });
                })
                ->lockForUpdate()
                ->first();

            if ($existingReservation !== null) {
                return [
                    'ticket' => $existingReservation,
                    'created' => false,
                ];
            }

            $ticket = Ticket::query()
                ->where('event_id', $event->id)
                ->where(function ($query) use ($now): void {
                    $query
                        ->where('status', Ticket::STATUS_AVAILABLE)
                        ->orWhere(function ($query) use ($now): void {
                            $query
                                ->where('status', Ticket::STATUS_RESERVED)
                                ->where('reserved_until', '<=', $now);
                        });
                })
                // PostgreSQL workers can reserve different rows concurrently without queueing.
                ->lock('for update skip locked')
                ->orderBy('id')
                ->first();

            if ($ticket === null) {
                return [
                    'ticket' => null,
                    'created' => false,
                ];
            }

            $ticket->forceFill([
                'status' => Ticket::STATUS_RESERVED,
                'user_id' => $userId,
                'reserved_until' => $now->copy()->addMinutes(5),
            ])->save();

            $ticket->refresh();

            return [
                'ticket' => $ticket,
                'created' => true,
            ];
        });
    }
}
