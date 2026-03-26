<?php

use App\Actions\Checkout\ReserveTicketAction;
use App\Models\Event;
use App\Models\Ticket;
use App\Services\Checkout\CheckoutAlreadyInProgressException;
use App\Services\Checkout\CheckoutLockService;
use App\Services\Checkout\TicketReservationService;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class);

it('returns a conflict result when sales have not started', function () {
    $lockService = mock(CheckoutLockService::class);
    $reservationService = mock(TicketReservationService::class);

    $action = new ReserveTicketAction($lockService, $reservationService);

    $event = new Event();
    $event->id = 10;
    $event->start_sales_at = now()->addMinute();

    $result = $action($event, 42);

    expect($result->successful())->toBeFalse()
        ->and($result->message())->toBe('Ticket sales have not started yet.')
        ->and($result->httpStatus())->toBe(409);
});

it('returns a created result when the reservation service reserves a ticket', function () {
    $ticket = new Ticket();
    $ticket->id = 100;
    $ticket->event_id = 10;
    $ticket->seat_number = 'A-1';
    $ticket->price = '99.99';
    $ticket->status = Ticket::STATUS_RESERVED;
    $ticket->user_id = 42;
    $ticket->reserved_until = now()->addMinutes(5);

    $lockService = mock(CheckoutLockService::class, function (MockInterface $mock) use ($ticket): void {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn([
                'ticket' => $ticket,
                'created' => true,
            ]);
    });

    $reservationService = mock(TicketReservationService::class);

    $action = new ReserveTicketAction($lockService, $reservationService);

    $event = new Event();
    $event->id = 10;
    $event->start_sales_at = now()->subMinute();

    $result = $action($event, 42);

    expect($result->successful())->toBeTrue()
        ->and($result->created())->toBeTrue()
        ->and($result->ticket?->id)->toBe(100)
        ->and($result->httpStatus())->toBe(201);
});

it('returns a conflict result when a checkout is already in progress', function () {
    $lockService = mock(CheckoutLockService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')
            ->once()
            ->andThrow(new CheckoutAlreadyInProgressException());
    });

    $reservationService = mock(TicketReservationService::class);

    $action = new ReserveTicketAction($lockService, $reservationService);

    $event = new Event();
    $event->id = 10;
    $event->start_sales_at = now()->subMinute();

    $result = $action($event, 42);

    expect($result->successful())->toBeFalse()
        ->and($result->message())->toBe('A checkout attempt is already in progress for this user.')
        ->and($result->httpStatus())->toBe(409);
});

it('returns a sold out result when no ticket can be reserved', function () {
    $lockService = mock(CheckoutLockService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('execute')
            ->once()
            ->andReturn([
                'ticket' => null,
                'created' => false,
            ]);
    });

    $reservationService = mock(TicketReservationService::class);

    $action = new ReserveTicketAction($lockService, $reservationService);

    $event = new Event();
    $event->id = 10;
    $event->start_sales_at = now()->subMinute();

    $result = $action($event, 42);

    expect($result->successful())->toBeFalse()
        ->and($result->message())->toBe('Tickets are sold out for this event.')
        ->and($result->httpStatus())->toBe(409);
});
