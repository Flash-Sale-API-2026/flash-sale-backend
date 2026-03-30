<?php

namespace App\Http\Controllers\Internal;

use App\Actions\Internal\ConfirmTicketReservationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\ConfirmTicketReservationRequest;
use App\Http\Resources\ConfirmedReservationResource;
use App\Http\Resources\MessageResource;
use App\Models\Ticket;
use App\Services\Checkout\TicketReservationNotConfirmableException;
use Symfony\Component\HttpFoundation\Response;

class ConfirmTicketReservationController extends Controller
{
    public function __invoke(
        ConfirmTicketReservationRequest $request,
        Ticket $ticket,
        ConfirmTicketReservationAction $confirmTicketReservationAction,
    ): Response {
        try {
            $reservation = $confirmTicketReservationAction($ticket, $request->userId());
        } catch (TicketReservationNotConfirmableException $exception) {
            return (new MessageResource([
                'message' => $exception->getMessage(),
            ]))
                ->response()
                ->setStatusCode(Response::HTTP_CONFLICT);
        }

        return (new ConfirmedReservationResource($reservation))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
