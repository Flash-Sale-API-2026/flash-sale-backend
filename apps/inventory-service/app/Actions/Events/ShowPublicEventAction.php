<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Services\Events\PublicEventViewService;

class ShowPublicEventAction
{
    public function __construct(
        private readonly PublicEventViewService $publicEventViewService,
    ) {
    }

    public function __invoke(int $eventId): Event
    {
        return $this->publicEventViewService->findOrFail($eventId);
    }
}
