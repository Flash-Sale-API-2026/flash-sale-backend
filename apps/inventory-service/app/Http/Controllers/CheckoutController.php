<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\ReserveTicketAction;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\CheckoutTicketResource;
use App\Http\Resources\MessageResource;
use App\Models\Event;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, Event $event, ReserveTicketAction $reserveTicketAction): Response
    {
        $result = $reserveTicketAction($event, $request->userId());

        if (! $result->successful()) {
            return (new MessageResource([
                'message' => $result->message(),
            ]))
                ->response()
                ->setStatusCode($result->httpStatus());
        }

        return (new CheckoutTicketResource($result->ticket))
            ->response()
            ->setStatusCode($result->httpStatus());
    }
}
