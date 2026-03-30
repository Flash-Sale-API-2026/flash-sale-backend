<?php

use App\Http\Controllers\Internal\ConfirmTicketReservationController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PublicEventController;
use Illuminate\Support\Facades\Route;

Route::get('/events', [PublicEventController::class, 'index']);
Route::get('/events/{event}', [PublicEventController::class, 'show']);

Route::post('/events/{event}/checkout', CheckoutController::class)
    ->middleware('throttle:checkout');

Route::post('/internal/tickets/{ticket}/reservation/confirm', ConfirmTicketReservationController::class);
