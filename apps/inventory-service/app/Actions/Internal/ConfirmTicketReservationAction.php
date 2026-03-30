<?php

namespace App\Actions\Internal;

use App\Models\Ticket;
use App\Services\Checkout\TicketReservationConfirmationService;

class ConfirmTicketReservationAction
{
    public function __construct(
        private readonly TicketReservationConfirmationService $ticketReservationConfirmationService,
    ) {
    }

    /**
     * @return array{ticket_id: int, event_id: int, user_id: int, amount: string, reserved_until: string}
     */
    public function __invoke(Ticket $ticket, int $userId): array
    {
        return $this->ticketReservationConfirmationService->confirm($ticket, $userId);
    }
}
