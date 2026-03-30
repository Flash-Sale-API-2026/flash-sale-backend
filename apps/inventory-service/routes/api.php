<?php

use App\Http\Controllers\Internal\ConfirmTicketReservationController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::post('/events/{event}/checkout', CheckoutController::class)
    ->middleware('throttle:checkout');

Route::post('/internal/tickets/{ticket}/reservation/confirm', ConfirmTicketReservationController::class);
