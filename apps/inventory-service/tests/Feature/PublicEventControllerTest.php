<?php

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-03-30 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('lists public events without authentication', function () {
    $event = Event::query()->create([
        'name' => 'Flash Sale',
        'total_tickets' => 4,
        'start_sales_at' => now()->subHour(),
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-1',
        'price' => 99.99,
        'status' => Ticket::STATUS_AVAILABLE,
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-2',
        'price' => 99.99,
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 7,
        'reserved_until' => now()->addMinutes(5),
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-3',
        'price' => 99.99,
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 8,
        'reserved_until' => now()->subMinute(),
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-4',
        'price' => 99.99,
        'status' => Ticket::STATUS_SOLD,
        'user_id' => 9,
    ]);

    $response = $this->getJson('/api/events?per_page=1');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', $event->id)
        ->assertJsonPath('data.0.available_tickets', 2)
        ->assertJsonPath('data.0.reserved_tickets', 1)
        ->assertJsonPath('data.0.sold_tickets', 1)
        ->assertJsonPath('data.0.sales_started', true)
        ->assertJsonPath('meta.per_page', 1);
});

it('shows a single public event summary without authentication', function () {
    $event = Event::query()->create([
        'name' => 'Future Sale',
        'total_tickets' => 2,
        'start_sales_at' => now()->addDay(),
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'B-1',
        'price' => 149.99,
        'status' => Ticket::STATUS_AVAILABLE,
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'B-2',
        'price' => 149.99,
        'status' => Ticket::STATUS_SOLD,
        'user_id' => 14,
    ]);

    $response = $this->getJson("/api/events/{$event->id}");

    $response
        ->assertOk()
        ->assertJsonPath('id', $event->id)
        ->assertJsonPath('name', 'Future Sale')
        ->assertJsonPath('available_tickets', 1)
        ->assertJsonPath('reserved_tickets', 0)
        ->assertJsonPath('sold_tickets', 1)
        ->assertJsonPath('sales_started', false);
});

it('validates public event list query params', function () {
    $response = $this->getJson('/api/events?per_page=0');

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});

it('returns not found for a missing public event', function () {
    $this->getJson('/api/events/999999')
        ->assertNotFound();
});
