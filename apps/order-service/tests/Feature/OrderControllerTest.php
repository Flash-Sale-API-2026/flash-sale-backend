<?php

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const ORDER_AUTH_HEADER = ['X-Internal-User-Id' => '42'];

it('creates an order for an authenticated user', function () {
    $response = $this->withHeaders(ORDER_AUTH_HEADER)->postJson('/api/orders', [
        'ticket_id' => 1001,
        'amount' => 149.99,
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
        'amount' => 0,
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'authenticated_user_id',
            'ticket_id',
            'amount',
        ]);
});

it('rejects unauthenticated order requests', function () {
    $response = $this->postJson('/api/orders', [
        'ticket_id' => 1001,
        'amount' => 149.99,
    ]);

    $response
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Only authenticated users can place orders.');
});
