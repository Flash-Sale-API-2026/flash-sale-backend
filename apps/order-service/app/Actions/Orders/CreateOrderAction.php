<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OutboxMessage;
use App\Services\Inventory\InventoryReservationClient;
use App\Services\Inventory\TicketAlreadyOrderedException;
use App\Services\Outbox\OrderCreatedOutboxMessageFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class CreateOrderAction
{
    public function __construct(
        private readonly InventoryReservationClient $inventoryReservationClient,
        private readonly OrderCreatedOutboxMessageFactory $orderCreatedOutboxMessageFactory,
    ) {
    }

    /**
     * @param  array{user_id: int, ticket_id: int}  $payload
     */
    public function __invoke(array $payload): Order
    {
        $existingOrder = Order::query()
            ->where('ticket_id', $payload['ticket_id'])
            ->first();

        if ($existingOrder !== null) {
            return $this->resolveExistingOrder($existingOrder, $payload['user_id']);
        }

        $reservation = $this->inventoryReservationClient->confirm(
            userId: $payload['user_id'],
            ticketId: $payload['ticket_id'],
        );

        try {
            return DB::transaction(function () use ($payload, $reservation): Order {
                $existingOrder = Order::query()
                    ->where('ticket_id', $payload['ticket_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder !== null) {
                    return $this->resolveExistingOrder($existingOrder, $payload['user_id']);
                }

                $order = Order::query()->create([
                    'user_id' => $payload['user_id'],
                    'ticket_id' => $payload['ticket_id'],
                    'amount' => $reservation['amount'],
                    'status' => Order::STATUS_PENDING,
                ]);

                OutboxMessage::query()->create(
                    $this->orderCreatedOutboxMessageFactory->make($order)
                );

                return $order->refresh();
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existingOrder = Order::query()
                ->where('ticket_id', $payload['ticket_id'])
                ->first();

            if ($existingOrder !== null) {
                return $this->resolveExistingOrder($existingOrder, $payload['user_id']);
            }

            throw $exception;
        }
    }

    private function resolveExistingOrder(Order $order, int $userId): Order
    {
        if ($order->user_id !== $userId) {
            throw new TicketAlreadyOrderedException('This ticket already has an order for another user.');
        }

        return $order;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505';
    }
}
