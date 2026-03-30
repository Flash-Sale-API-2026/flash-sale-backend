<?php

namespace App\Actions\Events;

use App\Services\Events\PublicEventViewService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPublicEventsAction
{
    public function __construct(
        private readonly PublicEventViewService $publicEventViewService,
    ) {
    }

    public function __invoke(int $perPage): LengthAwarePaginator
    {
        return $this->publicEventViewService->paginate($perPage);
    }
}
