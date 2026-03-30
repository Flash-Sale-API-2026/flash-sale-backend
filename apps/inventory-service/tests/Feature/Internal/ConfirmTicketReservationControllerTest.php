<?php

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const INTERNAL_SERVICE_HEADER = ['X-Internal-Service-Token' => 'flash-sale-internal-token'];

beforeEach(function (): void {
    config()->set('services.internal.token', 'flash-sale-internal-token');
});

it('confirms an active reservation for a trusted internal service', function () {
    $event = Event::query()->create([
        'name' => 'Rock Fest',
        'total_tickets' => 1,
        'start_sales_at' => now()->subHour(),
    ]);

    $ticket = Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-1',
        'price' => 149.99,
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 42,
        'reserved_until' => now()->addMinutes(5),
    ]);

    $response = $this->withHeaders(INTERNAL_SERVICE_HEADER)->postJson(
        "/api/internal/tickets/{$ticket->id}/reservation/confirm",
        ['user_id' => 42],
    );

    $response
        ->assertOk()
        ->assertJsonPath('ticket_id', $ticket->id)
        ->assertJsonPath('event_id', $event->id)
        ->assertJsonPath('user_id', 42)
        ->assertJsonPath('amount', '149.99');
});

it('rejects reservation confirmation requests without a trusted internal token', function () {
    $event = Event::query()->create([
        'name' => 'Rock Fest',
        'total_tickets' => 1,
        'start_sales_at' => now()->subHour(),
    ]);

    $ticket = Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-1',
        'price' => 149.99,
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 42,
        'reserved_until' => now()->addMinutes(5),
    ]);

    $response = $this->postJson("/api/internal/tickets/{$ticket->id}/reservation/confirm", [
        'user_id' => 42,
    ]);

    $response
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Only trusted internal services can confirm reservations.');
});

it('returns a conflict when the reservation cannot be confirmed', function () {
    $event = Event::query()->create([
        'name' => 'Rock Fest',
        'total_tickets' => 1,
        'start_sales_at' => now()->subHour(),
    ]);

    $ticket = Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-1',
        'price' => 149.99,
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 42,
        'reserved_until' => now()->subMinute(),
    ]);

    $response = $this->withHeaders(INTERNAL_SERVICE_HEADER)->postJson(
        "/api/internal/tickets/{$ticket->id}/reservation/confirm",
        ['user_id' => 42],
    );

    $response
        ->assertConflict()
        ->assertJsonPath('message', 'This reservation has already expired.');
});
