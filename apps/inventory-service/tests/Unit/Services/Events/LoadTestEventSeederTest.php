<?php

use App\Models\Event;
use App\Models\Ticket;
use App\Services\Events\LoadTestEventSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-03-31 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('creates a load test event with the requested number of available tickets', function () {
    $event = app(LoadTestEventSeeder::class)->create(
        ticketCount: 3,
        name: 'Load Test Event',
        salesStartedMinutesAgo: 10,
        seatPrefix: 'LOAD',
    );

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->name)->toBe('Load Test Event')
        ->and($event->total_tickets)->toBe(3)
        ->and($event->start_sales_at?->toIso8601String())->toBe(now()->subMinutes(10)->toIso8601String());

    expect(Ticket::query()->count())->toBe(3);

    $tickets = Ticket::query()
        ->where('event_id', $event->id)
        ->orderBy('id')
        ->get();

    expect($tickets->pluck('seat_number')->all())->toBe([
        'LOAD-000001',
        'LOAD-000002',
        'LOAD-000003',
    ])
        ->and($tickets->every(fn (Ticket $ticket): bool => $ticket->status === Ticket::STATUS_AVAILABLE))->toBeTrue();
});
