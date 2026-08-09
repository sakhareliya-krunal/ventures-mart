<?php

namespace App\Jobs;

use App\Mail\OrderCancellation;
use App\Models\Order;
use App\Services\ApplicationErrorRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderCancellationEmail implements ShouldQueue
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
            (new WithoutOverlapping('order-cancellation-email-'.$this->orderId))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        $order = Order::query()->with('items')->findOrFail($this->orderId);

        if (
            $order->status !== 'Cancelled'
            || $order->cancellation_emailed_at
            || ! filter_var($order->email, FILTER_VALIDATE_EMAIL)
        ) {
            return;
        }

        Mail::to($order->email)->send(new OrderCancellation($order));

        Order::query()
            ->whereKey($order->id)
            ->whereNull('cancellation_emailed_at')
            ->update(['cancellation_emailed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        if (! $exception) {
            return;
        }

        app(ApplicationErrorRecorder::class)->recordJobFailure(
            self::class,
            $exception,
            [
                'order_id' => $this->orderId,
                'phase' => 'order_cancellation_email',
            ],
        );
    }
}
