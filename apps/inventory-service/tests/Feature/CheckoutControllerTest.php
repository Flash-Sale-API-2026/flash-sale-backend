<?php

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const AUTH_HEADER = ['X-Internal-User-Id' => '42'];

it('reserves an available ticket for the requested user', function () {
    $event = Event::query()->create([
        'name' => 'Flash Sale',
        'total_tickets' => 2,
        'start_sales_at' => now()->subMinute(),
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-1',
        'price' => 99.99,
        'status' => Ticket::STATUS_AVAILABLE,
    ]);

    $response = $this->withHeaders(AUTH_HEADER)
        ->postJson("/api/events/{$event->id}/checkout");

    $response
        ->assertCreated()
        ->assertJsonPath('event_id', $event->id)
        ->assertJsonPath('seat_number', 'A-1')
        ->assertJsonPath('status', Ticket::STATUS_RESERVED)
        ->assertJsonPath('user_id', 42);

    $this->assertDatabaseHas('tickets', [
        'event_id' => $event->id,
        'seat_number' => 'A-1',
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 42,
    ]);
});

it('returns an existing active reservation for the same user and event', function () {
    $event = Event::query()->create([
        'name' => 'Flash Sale',
        'total_tickets' => 2,
        'start_sales_at' => now()->subMinute(),
    ]);

    $ticket = Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-1',
        'price' => 99.99,
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 42,
        'reserved_until' => now()->addMinutes(4),
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-2',
        'price' => 99.99,
        'status' => Ticket::STATUS_AVAILABLE,
    ]);

    $response = $this->withHeaders(AUTH_HEADER)
        ->postJson("/api/events/{$event->id}/checkout");

    $response
        ->assertOk()
        ->assertJsonPath('ticket_id', $ticket->id)
        ->assertJsonPath('seat_number', 'A-1')
        ->assertJsonPath('status', Ticket::STATUS_RESERVED);

    $this->assertDatabaseHas('tickets', [
        'event_id' => $event->id,
        'seat_number' => 'A-2',
        'status' => Ticket::STATUS_AVAILABLE,
        'user_id' => null,
    ]);
});

it('returns a conflict when no ticket can be reserved', function () {
    $event = Event::query()->create([
        'name' => 'Flash Sale',
        'total_tickets' => 1,
        'start_sales_at' => now()->subMinute(),
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-1',
        'price' => 99.99,
        'status' => Ticket::STATUS_SOLD,
        'user_id' => 7,
    ]);

    $response = $this->withHeaders(AUTH_HEADER)
        ->postJson("/api/events/{$event->id}/checkout");

    $response
        ->assertConflict()
        ->assertJsonPath('message', 'Tickets are sold out for this event.');
});

it('validates the checkout request payload', function () {
    $event = Event::query()->create([
        'name' => 'Flash Sale',
        'total_tickets' => 1,
        'start_sales_at' => now()->subMinute(),
    ]);

    $response = $this->withHeaders([
        'X-Internal-User-Id' => 'invalid-user-id',
    ])->postJson("/api/events/{$event->id}/checkout");

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['authenticated_user_id']);
});

it('rejects unauthenticated checkout requests', function () {
    $event = Event::query()->create([
        'name' => 'Flash Sale',
        'total_tickets' => 1,
        'start_sales_at' => now()->subMinute(),
    ]);

    $response = $this->postJson("/api/events/{$event->id}/checkout");

    $response
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Only authenticated users can reserve seats.');
});
