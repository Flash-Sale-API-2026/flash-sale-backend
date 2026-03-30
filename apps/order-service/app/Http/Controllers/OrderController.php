<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\OrderResource;
use App\Services\Inventory\InventoryReservationConfirmationException;
use App\Services\Inventory\TicketAlreadyOrderedException;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, CreateOrderAction $createOrderAction): Response
    {
        try {
            $order = $createOrderAction($request->orderData());
        } catch (InventoryReservationConfirmationException|TicketAlreadyOrderedException $exception) {
            return (new MessageResource([
                'message' => $exception->getMessage(),
            ]))
                ->response()
                ->setStatusCode(Response::HTTP_CONFLICT);
        }

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
