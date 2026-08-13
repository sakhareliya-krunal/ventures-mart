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

        if (
            $order->fulfillment_method !== FulfillmentMethod::Shiprocket
            || $order->shipping_notification_emailed_at
            || in_array($order->status, ['Cancelled', 'InventoryHold'], true)
            || $shipment?->cancelled_at
            || blank($shipment?->awb_code)
            || ! filter_var($order->email, FILTER_VALIDATE_EMAIL)
        ) {
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
