<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ShiprocketFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class FulfillShiprocketOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 1800];

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
        $order = Order::query()->with(['items', 'shiprocketShipment'])->findOrFail($this->orderId);
        $fulfillment->fulfill($order);
    }
}
