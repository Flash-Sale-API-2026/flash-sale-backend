<?php

use App\Actions\Orders\CreateOrderAction;
use App\Models\Order;
use App\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a pending order and transactional outbox message', function () {
    $action = app(CreateOrderAction::class);

    $order = $action([
        'user_id' => 42,
        'ticket_id' => 1001,
        'amount' => '149.99',
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
        'aggregate_type' => 'order',
        'aggregate_id' => $order->id,
        'type' => 'TicketReservedEvent',
    ]);

    $outboxMessage = OutboxMessage::query()->where('aggregate_id', $order->id)->firstOrFail();

    expect($outboxMessage->payload)->toMatchArray([
        'order_id' => $order->id,
        'user_id' => 42,
        'ticket_id' => 1001,
        'status' => Order::STATUS_PENDING,
    ]);
});
