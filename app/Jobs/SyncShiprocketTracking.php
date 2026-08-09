<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ShiprocketFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncShiprocketTracking implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $orderId)
    {
        $this->afterCommit();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('shiprocket-order-'.$this->orderId))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(ShiprocketFulfillmentService $fulfillment): void
    {
        $order = Order::query()->with('shiprocketShipment')->findOrFail($this->orderId);
        $fulfillment->syncTracking($order);
    }
}
