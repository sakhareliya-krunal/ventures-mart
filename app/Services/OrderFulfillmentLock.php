<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class OrderFulfillmentLock
{
    public function run(int $orderId, Closure $callback, int $waitSeconds = 10): mixed
    {
        try {
            return Cache::lock($this->key($orderId), 300)->block($waitSeconds, $callback);
        } catch (LockTimeoutException) {
            throw new RuntimeException('This order fulfillment is currently being updated. Please retry.');
        }
    }

    private function key(int $orderId): string
    {
        return 'order-fulfillment:'.$orderId;
    }
}
