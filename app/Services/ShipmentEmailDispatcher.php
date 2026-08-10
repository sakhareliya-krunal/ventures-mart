<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Jobs\SendShipmentTrackingEmail;
use App\Models\Order;
use App\Models\ShiprocketShipment;
use Throwable;

class ShipmentEmailDispatcher
{
    public function dispatch(Order $order, ?ShiprocketShipment $shipment = null): void
    {
        $shipment ??= $order->shiprocketShipment;

        $skipReason = null;
        if ($order->fulfillment_method !== FulfillmentMethod::Shiprocket) {
            $skipReason = 'not_shiprocket';
        } elseif ($order->shipping_notification_emailed_at) {
            $skipReason = 'already_emailed';
        } elseif (in_array($order->status, ['Cancelled', 'InventoryHold'], true)) {
            $skipReason = 'status_'.$order->status;
        } elseif ($shipment?->cancelled_at) {
            $skipReason = 'shipment_cancelled';
        } elseif (blank($shipment?->awb_code)) {
            $skipReason = 'no_awb';
        } elseif (! filter_var($order->email, FILTER_VALIDATE_EMAIL)) {
            $skipReason = 'invalid_email';
        }

        // #region agent log
        file_put_contents(base_path('debug-8efceb.log'), json_encode([
            'sessionId' => '8efceb',
            'runId' => 'pre-fix',
            'hypothesisId' => 'C',
            'location' => 'ShipmentEmailDispatcher.php:dispatch',
            'message' => $skipReason ? 'tracking_enqueue_skipped' : 'tracking_enqueue_attempt',
            'data' => [
                'order_id' => $order->id,
                'awb' => $shipment?->awb_code,
                'shipping_notification_emailed_at' => $order->shipping_notification_emailed_at,
                'skip_reason' => $skipReason,
            ],
            'timestamp' => (int) (microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
        // #endregion

        if ($skipReason) {
            return;
        }

        try {
            SendShipmentTrackingEmail::dispatch($order->id);
        } catch (Throwable $exception) {
            app(ApplicationErrorRecorder::class)->recordThrowable($exception, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'phase' => 'shipment_tracking_email_enqueue',
            ], 'email');
        }
    }
}
