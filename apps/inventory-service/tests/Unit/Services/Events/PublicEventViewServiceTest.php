<?php

use App\Models\Event;
use App\Models\Ticket;
use App\Services\Events\PublicEventViewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-03-30 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('paginates public event summaries with reservable counts and deterministic ordering', function () {
    $laterEvent = Event::query()->create([
        'name' => 'Later Event',
        'total_tickets' => 1,
        'start_sales_at' => now()->addDay(),
    ]);

    Ticket::query()->create([
        'event_id' => $laterEvent->id,
        'seat_number' => 'L-1',
        'price' => 120.00,
        'status' => Ticket::STATUS_AVAILABLE,
    ]);

    $earlierEvent = Event::query()->create([
        'name' => 'Earlier Event',
        'total_tickets' => 4,
        'start_sales_at' => now()->subHour(),
    ]);

    Ticket::query()->create([
        'event_id' => $earlierEvent->id,
        'seat_number' => 'E-1',
        'price' => 99.99,
        'status' => Ticket::STATUS_AVAILABLE,
    ]);

    Ticket::query()->create([
        'event_id' => $earlierEvent->id,
        'seat_number' => 'E-2',
        'price' => 99.99,
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 10,
        'reserved_until' => now()->addMinutes(5),
    ]);

    Ticket::query()->create([
        'event_id' => $earlierEvent->id,
        'seat_number' => 'E-3',
        'price' => 99.99,
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 11,
        'reserved_until' => now()->subMinute(),
    ]);

    Ticket::query()->create([
        'event_id' => $earlierEvent->id,
        'seat_number' => 'E-4',
        'price' => 99.99,
        'status' => Ticket::STATUS_SOLD,
        'user_id' => 12,
    ]);

    $paginator = app(PublicEventViewService::class)->paginate(1);

    expect($paginator->total())->toBe(2)
        ->and($paginator->perPage())->toBe(1)
        ->and($paginator->items())->toHaveCount(1);

    $event = $paginator->items()[0];

    expect($event->id)->toBe($earlierEvent->id)
        ->and($event->available_tickets_count)->toBe(2)
        ->and($event->reserved_tickets_count)->toBe(1)
        ->and($event->sold_tickets_count)->toBe(1);
});

it('returns a single public event summary with aggregated availability', function () {
    $event = Event::query()->create([
        'name' => 'Flash Sale',
        'total_tickets' => 3,
        'start_sales_at' => now()->addHour(),
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-1',
        'price' => 149.99,
        'status' => Ticket::STATUS_AVAILABLE,
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-2',
        'price' => 149.99,
        'status' => Ticket::STATUS_RESERVED,
        'user_id' => 21,
        'reserved_until' => now()->addMinutes(10),
    ]);

    Ticket::query()->create([
        'event_id' => $event->id,
        'seat_number' => 'A-3',
        'price' => 149.99,
        'status' => Ticket::STATUS_SOLD,
        'user_id' => 22,
    ]);

    $summary = app(PublicEventViewService::class)->findOrFail($event->id);

    expect($summary->id)->toBe($event->id)
        ->and($summary->available_tickets_count)->toBe(1)
        ->and($summary->reserved_tickets_count)->toBe(1)
        ->and($summary->sold_tickets_count)->toBe(1);
});
