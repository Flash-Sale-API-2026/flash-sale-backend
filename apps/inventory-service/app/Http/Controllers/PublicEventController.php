<?php

namespace App\Http\Controllers;

use App\Actions\Events\ListPublicEventsAction;
use App\Actions\Events\ShowPublicEventAction;
use App\Http\Requests\ListPublicEventsRequest;
use App\Http\Resources\PublicEventResource;
use App\Models\Event;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicEventController extends Controller
{
    public function index(
        ListPublicEventsRequest $request,
        ListPublicEventsAction $listPublicEventsAction,
    ): AnonymousResourceCollection {
        $events = $listPublicEventsAction($request->perPage());

        return PublicEventResource::collection($events);
    }

    public function show(Event $event, ShowPublicEventAction $showPublicEventAction): PublicEventResource
    {
        return new PublicEventResource($showPublicEventAction($event->getKey()));
    }
}
