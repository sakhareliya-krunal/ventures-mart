<?php

namespace App\Jobs;

use App\Enums\FulfillmentMethod;
use App\Mail\ShipmentTracking;
use App\Models\Order;
use App\Services\ApplicationErrorRecorder;
use App\Services\OrderShipmentDetails;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendShipmentTrackingEmail implements ShouldQueue
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
            (new WithoutOverlapping('shipment-tracking-email-'.$this->orderId))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(OrderShipmentDetails $shipments): void
    {
        $order = Order::query()->with('shiprocketShipment')->findOrFail($this->orderId);
        $shipment = $order->shiprocketShipment;

        if (
            $order->fulfillment_method !== FulfillmentMethod::Shiprocket
            || $order->shipping_notification_emailed_at
            || in_array($order->status, ['Cancelled', 'InventoryHold'], true)
            || $shipment?->cancelled_at
            || blank($shipment?->awb_code)
            || ! filter_var($order->email, FILTER_VALIDATE_EMAIL)
        ) {
            // #region agent log
            file_put_contents(base_path('debug-8efceb.log'), json_encode([
                'sessionId' => '8efceb',
                'runId' => 'pre-fix',
                'hypothesisId' => 'C',
                'location' => 'SendShipmentTrackingEmail.php:handle',
                'message' => 'tracking_should_send_false',
                'data' => [
                    'order_id' => $order->id,
                    'awb' => $shipment?->awb_code,
                    'shipping_notification_emailed_at' => $order->shipping_notification_emailed_at,
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
            // #endregion

            return;
        }

        $details = $shipments->forCustomer($order);
        Mail::to($order->email)->send(new ShipmentTracking($order, $details));

        Order::query()
            ->whereKey($order->id)
            ->whereNull('shipping_notification_emailed_at')
            ->update(['shipping_notification_emailed_at' => now()]);

        // #region agent log
        file_put_contents(base_path('debug-8efceb.log'), json_encode([
            'sessionId' => '8efceb',
            'runId' => 'pre-fix',
            'hypothesisId' => 'C',
            'location' => 'SendShipmentTrackingEmail.php:handle',
            'message' => 'tracking_mail_sent',
            'data' => [
                'order_id' => $order->id,
                'to' => $order->email,
                'awb' => $shipment?->awb_code,
                'mailer' => (string) config('mail.default'),
                'subject_hint' => 'Your order is ready to track',
            ],
            'timestamp' => (int) (microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
        // #endregion
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            app(ApplicationErrorRecorder::class)->recordJobFailure(
                self::class,
                $exception,
                [
                    'order_id' => $this->orderId,
                    'phase' => 'shipment_tracking_email',
                ],
            );
        }
    }
}
