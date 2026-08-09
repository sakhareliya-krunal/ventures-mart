<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessShiprocketWebhook;
use App\Models\ShipmentWebhookEvent;
use App\Models\ShiprocketShipment;
use Illuminate\Http\Request;

class ShiprocketWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $configuredToken = trim((string) config('services.shiprocket.webhook_token'));
        $providedToken = trim((string) $request->header('x-api-key'));

        if (
            $configuredToken === ''
            || $providedToken === ''
            || ! hash_equals($configuredToken, $providedToken)
        ) {
            return response()->json(['message' => 'Unauthorized webhook.'], 401);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        $awb = trim((string) ($payload['awb'] ?? ''));
        $remoteOrderId = trim((string) ($payload['sr_order_id'] ?? ''));
        $statusId = (string) ($payload['shipment_status_id'] ?? $payload['current_status_id'] ?? '');
        $status = trim((string) ($payload['current_status'] ?? $payload['shipment_status'] ?? ''));
        $timestamp = trim((string) ($payload['current_timestamp'] ?? ''));
        $eventKey = hash('sha256', implode('|', [
            'shiprocket',
            $awb,
            $remoteOrderId,
            $statusId,
            $status,
            $timestamp,
        ]));

        $shipment = ShiprocketShipment::query()
            ->when(
                $awb !== '',
                fn ($query) => $query->where('awb_code', $awb),
                fn ($query) => $query->where('shiprocket_order_id', $remoteOrderId),
            )
            ->first();

        $event = ShipmentWebhookEvent::query()->firstOrCreate(
            ['external_event_key' => $eventKey],
            [
                'provider' => 'shiprocket',
                'order_id' => $shipment?->order_id,
                'shiprocket_shipment_id' => $shipment?->id,
                'awb' => $awb ?: null,
                'remote_order_id' => $remoteOrderId ?: null,
                'event_type' => $status ?: 'tracking_update',
                'provider_status_id' => $statusId ?: null,
                'status' => 'received',
                'payload' => $payload,
            ],
        );

        if ($event->wasRecentlyCreated || in_array($event->status, ['received', 'failed'], true)) {
            ProcessShiprocketWebhook::dispatch($event->id);
        }

        return response()->json(['status' => 'accepted']);
    }
}
