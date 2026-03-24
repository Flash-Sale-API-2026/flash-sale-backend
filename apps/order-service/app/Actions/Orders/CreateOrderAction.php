<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    /**
     * @param  array{user_id: int, ticket_id: int, amount: string}  $payload
     */
    public function __invoke(array $payload): Order
    {
        return DB::transaction(function () use ($payload): Order {
            $order = Order::query()->create([
                'user_id' => $payload['user_id'],
                'ticket_id' => $payload['ticket_id'],
                'amount' => $payload['amount'],
                'status' => Order::STATUS_PENDING,
            ]);

            OutboxMessage::query()->create([
                'aggregate_type' => 'order',
                'aggregate_id' => $order->id,
                'type' => 'TicketReservedEvent',
                'payload' => [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'ticket_id' => $order->ticket_id,
                    'amount' => $order->amount,
                    'status' => $order->status,
                ],
                'created_at' => now(),
                'processed_at' => null,
            ]);

            return $order->refresh();
        });
    }
}
