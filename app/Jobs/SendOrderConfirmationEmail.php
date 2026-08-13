<?php

namespace App\Jobs;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Services\ApplicationErrorRecorder;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderConfirmationEmail implements ShouldQueue
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
            (new WithoutOverlapping('order-confirmation-email-'.$this->orderId))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(InvoiceService $invoices): void
    {
        $order = Order::query()
            ->with(['items', 'shiprocketShipment'])
            ->findOrFail($this->orderId);

        if (! $this->shouldSend($order)) {
            return;
        }

        $document = $invoices->pdfDocument($order);
        $issuedOrder = $document['order']->loadMissing('items');

        Mail::to($issuedOrder->email)->send(new OrderConfirmation(
            $issuedOrder,
            $document['contents'],
            $document['filename'],
        ));

        Order::query()
            ->whereKey($issuedOrder->id)
            ->whereNull('order_confirmation_emailed_at')
            ->update(['order_confirmation_emailed_at' => now()]);
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
                'phase' => 'order_confirmation_email',
            ],
        );
    }

    private function shouldSend(Order $order): bool
    {
        if (
            $order->order_confirmation_emailed_at
            || ! filter_var($order->email, FILTER_VALIDATE_EMAIL)
            || in_array($order->status, ['Cancelled', 'InventoryHold'], true)
        ) {
            return false;
        }

        if ($order->payment_method === 'cod') {
            return in_array($order->status, ['Processing', 'Packed', 'Shipped', 'Delivered'], true);
        }

        return $order->payment_method === 'razorpay'
            && $order->payment_status === 'paid';
    }
}
