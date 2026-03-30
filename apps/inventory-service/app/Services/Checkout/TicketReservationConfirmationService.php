<?php

namespace App\Services\Checkout;

use App\Models\Ticket;

class TicketReservationConfirmationService
{
    /**
     * @return array{ticket_id: int, event_id: int, user_id: int, amount: string, reserved_until: string}
     */
    public function confirm(Ticket $ticket, int $userId): array
    {
        $ticket->refresh();

        if ($ticket->status !== Ticket::STATUS_RESERVED) {
            throw new TicketReservationNotConfirmableException('This ticket is not currently reserved.');
        }

        if ($ticket->user_id !== $userId) {
            throw new TicketReservationNotConfirmableException('This reservation does not belong to the authenticated user.');
        }

        if ($ticket->reserved_until === null || $ticket->reserved_until->lte(now())) {
            throw new TicketReservationNotConfirmableException('This reservation has already expired.');
        }

        return [
            'ticket_id' => $ticket->id,
            'event_id' => $ticket->event_id,
            'user_id' => $ticket->user_id,
            'amount' => (string) $ticket->price,
            'reserved_until' => $ticket->reserved_until->toIso8601String(),
        ];
    }
}
