<?php

namespace App\Services\Checkout;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class CheckoutLockService
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function execute(int $eventId, int $userId, Closure $callback): mixed
    {
        $lock = Cache::lock("checkout:event:{$eventId}:user:{$userId}", 5);

        try {
            return $lock->block(0, $callback);
        } catch (LockTimeoutException $exception) {
            throw new CheckoutAlreadyInProgressException(
                'A checkout attempt is already in progress for this user.',
                previous: $exception,
            );
        } finally {
            rescue(static fn () => $lock->release(), report: false);
        }
    }
}
