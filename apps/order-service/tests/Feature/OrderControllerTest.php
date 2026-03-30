<?php

use App\Models\Order;
use App\Services\Inventory\InventoryReservationClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

const ORDER_AUTH_HEADER = ['X-Internal-User-Id' => '42'];

it('creates an order for an authenticated user', function () {
    $this->mock(InventoryReservationClient::class, function (MockInterface $mock): void {
        $mock
            ->shouldReceive('confirm')
            ->once()
            ->with(42, 1001)
            ->andReturn([
                'ticket_id' => 1001,
                'event_id' => 1,
                'user_id' => 42,
                'amount' => '149.99',
                'reserved_until' => now()->addMinutes(5)->toIso8601String(),
            ]);
    });

    $response = $this->withHeaders(ORDER_AUTH_HEADER)->postJson('/api/orders', [
        'ticket_id' => 1001,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('user_id', 42)
        ->assertJsonPath('ticket_id', 1001)
        ->assertJsonPath('amount', '149.99')
        ->assertJsonPath('status', Order::STATUS_PENDING);

    $this->assertDatabaseHas('orders', [
        'user_id' => 42,
        'ticket_id' => 1001,
        'status' => Order::STATUS_PENDING,
    ]);
});

it('validates the trusted user header and request payload', function () {
    $response = $this->withHeaders([
        'X-Internal-User-Id' => 'invalid-user-id',
    ])->postJson('/api/orders', [
        'ticket_id' => 0,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'authenticated_user_id',
            'ticket_id',
        ]);
});

it('returns a conflict when inventory cannot confirm the reservation', function () {
    $this->mock(InventoryReservationClient::class, function (MockInterface $mock): void {
        $mock
            ->shouldReceive('confirm')
            ->once()
            ->with(42, 1001)
            ->andThrow(new \App\Services\Inventory\InventoryReservationConfirmationException('This reservation has already expired.'));
    });

    $response = $this->withHeaders(ORDER_AUTH_HEADER)->postJson('/api/orders', [
        'ticket_id' => 1001,
    ]);

    $response
        ->assertConflict()
        ->assertJsonPath('message', 'This reservation has already expired.');
});

it('rejects unauthenticated order requests', function () {
    $response = $this->postJson('/api/orders', [
        'ticket_id' => 1001,
    ]);

    $response
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Only authenticated users can place orders.');
});
