<?php

namespace App\Actions\Checkout;

use App\Models\Event;
use App\Services\Checkout\CheckoutAlreadyInProgressException;
use App\Services\Checkout\CheckoutLockService;
use App\Services\Checkout\TicketReservationService;

class ReserveTicketAction
{
    public function __construct(
        private readonly CheckoutLockService $checkoutLockService,
        private readonly TicketReservationService $ticketReservationService,
    ) {
    }

    public function __invoke(Event $event, int $userId): CheckoutResult
    {
        if ($event->start_sales_at->isFuture()) {
            return CheckoutResult::salesNotStarted();
        }

        try {
            $reservation = $this->checkoutLockService->execute(
                eventId: $event->id,
                userId: $userId,
                callback: fn (): array => $this->ticketReservationService->reserve($event, $userId),
            );
        } catch (CheckoutAlreadyInProgressException) {
            return CheckoutResult::alreadyInProgress();
        }

        if ($reservation['ticket'] === null) {
            return CheckoutResult::soldOut();
        }

        return $reservation['created']
            ? CheckoutResult::reserved($reservation['ticket'])
            : CheckoutResult::alreadyReserved($reservation['ticket']);
    }
}
