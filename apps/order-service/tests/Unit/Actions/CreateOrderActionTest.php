<?php

use App\Actions\Orders\CreateOrderAction;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Services\Inventory\InventoryReservationClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a pending order and transactional outbox message', function () {
    $this->mock(InventoryReservationClient::class, function (MockInterface $mock): void {
        $mock
            ->shouldReceive('confirm')
            ->once()
            ->with(42, 1001)
            ->andReturn([
                'ticket_id' => 1001,
                'event_id' => 501,
                'user_id' => 42,
                'amount' => '149.99',
                'reserved_until' => now()->addMinutes(5)->toIso8601String(),
            ]);
    });

    $action = app(CreateOrderAction::class);

    $order = $action([
        'user_id' => 42,
        'ticket_id' => 1001,
    ]);

    expect($order)->toBeInstanceOf(Order::class)
        ->and($order->status)->toBe(Order::STATUS_PENDING)
        ->and($order->user_id)->toBe(42)
        ->and($order->ticket_id)->toBe(1001)
        ->and($order->amount)->toBe('149.99');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'user_id' => 42,
        'ticket_id' => 1001,
        'status' => Order::STATUS_PENDING,
    ]);

    $this->assertDatabaseHas('outbox_messages', [
        'aggregate_type' => 'orders',
        'aggregate_id' => $order->id,
        'type' => 'order.created',
    ]);

    $outboxMessage = OutboxMessage::query()->where('aggregate_id', $order->id)->firstOrFail();

    expect(Str::isUuid($outboxMessage->event_id))->toBeTrue()
        ->and($outboxMessage->payload)->toMatchArray([
            'event_id' => $outboxMessage->event_id,
            'event_type' => 'order.created',
            'order' => [
                'id' => $order->id,
                'user_id' => 42,
                'ticket_id' => 1001,
                'amount' => '149.99',
                'status' => Order::STATUS_PENDING,
            ],
        ]);
});
