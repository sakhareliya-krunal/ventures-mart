<?php

namespace App\Services;

use App\Models\Order;

class OrderShipmentDetails
{
    /**
     * @return array<string, mixed>
     */
    public function forCustomer(Order $order): array
    {
        $order->loadMissing('shiprocketShipment');
        $shipment = $order->shiprocketShipment;
        $trackingUrl = $this->safeTrackingUrl($shipment?->tracking_url);
        $awb = $shipment?->awb_code ?: $order->awb_number ?: $order->tracking_number;

        $details = [
            'partner' => $shipment?->courier_name ?: $order->courier_partner,
            'tracking_id' => $awb,
            'awb_number' => $awb,
            'tracking_number' => $order->tracking_number ?: $awb,
            'tracking_url' => $trackingUrl,
            'shipment_status' => $shipment?->shipment_status,
            'pickup_status' => $shipment?->pickup_status,
            'awb_assigned_at' => $shipment?->awb_assigned_at?->toIso8601String(),
            'pickup_scheduled_at' => $shipment?->pickup_scheduled_at?->toIso8601String(),
            'last_synced_at' => $shipment?->last_synced_at?->toIso8601String(),
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'expected_delivery_at' => $order->expected_delivery_at?->toIso8601String(),
        ];

        $details['has_details'] = collect($details)
            ->except(['has_details'])
            ->contains(fn ($value) => filled($value));

        return $details;
    }

    private function safeTrackingUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > 1000 || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        return $url;
    }
}
