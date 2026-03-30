<?php

namespace App\Services\Outbox;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class OrderCreatedOutboxMessageFactory
{
    public const AGGREGATE_TYPE = 'orders';

    public const EVENT_TYPE = 'order.created';

    /**
     * @return array<string, mixed>
     */
    public function make(Order $order): array
    {
        $eventId = (string) Str::uuid();
        $occurredAt = CarbonImmutable::now();

        return [
            'event_id' => $eventId,
            'aggregate_type' => self::AGGREGATE_TYPE,
            'aggregate_id' => $order->id,
            'type' => self::EVENT_TYPE,
            'payload' => [
                'event_id' => $eventId,
                'event_type' => self::EVENT_TYPE,
                'occurred_at' => $occurredAt->toIso8601String(),
                'order' => [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'ticket_id' => $order->ticket_id,
                    'amount' => (string) $order->amount,
                    'status' => $order->status,
                ],
            ],
            'created_at' => $occurredAt,
        ];
    }
}
