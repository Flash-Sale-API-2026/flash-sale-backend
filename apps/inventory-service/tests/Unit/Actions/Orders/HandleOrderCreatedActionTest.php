<?php

use App\Actions\Orders\HandleOrderCreatedAction;
use App\Models\Event;
use App\Models\InboxMessage;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('marks a reserved ticket as sold and records a processed inbox message', function () {
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

    $payload = [
        'event_id' => (string) Str::uuid(),
        'event_type' => 'order.created',
        'occurred_at' => now()->toIso8601String(),
        'order' => [
            'id' => 501,
            'user_id' => 42,
            'ticket_id' => $ticket->id,
            'amount' => '149.99',
            'status' => 'pending',
        ],
    ];

    app(HandleOrderCreatedAction::class)($payload);

    expect($ticket->fresh())
        ->status->toBe(Ticket::STATUS_SOLD)
        ->reserved_until->toBeNull();

    $this->assertDatabaseHas('inbox_messages', [
        'event_id' => $payload['event_id'],
        'event_type' => 'order.created',
        'status' => InboxMessage::STATUS_PROCESSED,
    ]);
});

it('is idempotent for an already processed event id', function () {
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

    $payload = [
        'event_id' => (string) Str::uuid(),
        'event_type' => 'order.created',
        'occurred_at' => now()->toIso8601String(),
        'order' => [
            'id' => 501,
            'user_id' => 42,
            'ticket_id' => $ticket->id,
            'amount' => '149.99',
            'status' => 'pending',
        ],
    ];

    $action = app(HandleOrderCreatedAction::class);

    $action($payload);
    $action($payload);

    expect(InboxMessage::query()->where('event_id', $payload['event_id'])->count())->toBe(1);
});

it('records a rejected inbox message when the reservation was already expired', function () {
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

    $payload = [
        'event_id' => (string) Str::uuid(),
        'event_type' => 'order.created',
        'occurred_at' => now()->toIso8601String(),
        'order' => [
            'id' => 501,
            'user_id' => 42,
            'ticket_id' => $ticket->id,
            'amount' => '149.99',
            'status' => 'pending',
        ],
    ];

    app(HandleOrderCreatedAction::class)($payload);

    expect($ticket->fresh()->status)->toBe(Ticket::STATUS_RESERVED);

    $this->assertDatabaseHas('inbox_messages', [
        'event_id' => $payload['event_id'],
        'event_type' => 'order.created',
        'status' => InboxMessage::STATUS_REJECTED,
        'failure_reason' => 'The reservation had already expired when the order was created.',
    ]);
});
