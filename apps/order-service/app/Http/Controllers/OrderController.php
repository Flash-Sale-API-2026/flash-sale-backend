<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, CreateOrderAction $createOrderAction): Response
    {
        return (new OrderResource($createOrderAction($request->orderData())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
