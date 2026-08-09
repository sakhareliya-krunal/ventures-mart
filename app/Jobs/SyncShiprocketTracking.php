<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\ApplicationErrorRecorder;
use App\Services\OrderFulfillmentLock;
use App\Services\ShiprocketFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

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

    public function handle(
        ShiprocketFulfillmentService $fulfillment,
        OrderFulfillmentLock $lock,
    ): void {
        $lock->run($this->orderId, function () use ($fulfillment): void {
            $order = Order::query()->with('shiprocketShipment')->findOrFail($this->orderId);
            $fulfillment->syncTracking($order);
        });
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            app(ApplicationErrorRecorder::class)->recordJobFailure(
                self::class,
                $exception,
                ['order_id' => $this->orderId],
            );
        }
    }
}
