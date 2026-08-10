<?php

namespace App\Http\Resources;

use App\Enums\FulfillmentMethod;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\OrderCancellationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class AdminOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'invoice_number' => $this->invoice_number,
            'invoice_issued_at' => $this->invoice_issued_at?->toIso8601String(),
            'invoice_available' => app(InvoiceService::class)->isInvoiceable($this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
            'status' => $this->status,
            'inventory_status' => $this->inventory_status?->value,
            'fulfillment_method' => $this->fulfillment_method?->value,
            'order_type' => $this->order_type ?: 'standard',
            'parent_order_id' => $this->parent_order_id,
            'can_switch_to_manual' => $this->canSwitchToManual(),
            'can_cancel' => app(OrderCancellationService::class)->canAdminCancel($this->resource),
            'can_delete' => $this->resource->canBeDeletedByAdmin(),
            'can_mark_refunded' => $this->payment_status === 'refund_pending',
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'razorpay_order_id' => $this->razorpay_order_id,
            'razorpay_payment_id' => $this->razorpay_payment_id,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'payment_expires_at' => $this->payment_expires_at?->toIso8601String(),
            'cancel_requested_at' => $this->cancel_requested_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'courier_partner' => $this->courier_partner,
            'awb_number' => $this->awb_number,
            'tracking_number' => $this->tracking_number,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'expected_delivery_at' => $this->expected_delivery_at?->toIso8601String(),
            'shiprocket' => $this->whenLoaded('shiprocketShipment', function () {
                $shipment = $this->shiprocketShipment;
                if (! $shipment) {
                    return null;
                }

                return [
                    'sync_status' => $shipment->sync_status,
                    'stage' => $shipment->stage,
                    'shiprocket_order_id' => $shipment->shiprocket_order_id,
                    'shipment_id' => $shipment->shipment_id,
                    'courier_company_id' => $shipment->courier_company_id,
                    'courier_name' => $shipment->courier_name,
                    'awb_code' => $shipment->awb_code,
                    'pickup_status' => $shipment->pickup_status,
                    'shipment_status' => $shipment->shipment_status,
                    'tracking_url' => $shipment->tracking_url,
                    'attempts' => (int) $shipment->attempts,
                    'last_error' => $shipment->last_error,
                    'order_created_at' => $shipment->order_created_at?->toIso8601String(),
                    'awb_assigned_at' => $shipment->awb_assigned_at?->toIso8601String(),
                    'pickup_scheduled_at' => $shipment->pickup_scheduled_at?->toIso8601String(),
                    'last_synced_at' => $shipment->last_synced_at?->toIso8601String(),
                    'cancelled_at' => $shipment->cancelled_at?->toIso8601String(),
                    'tracking_history' => $shipment->relationLoaded('trackingEvents')
                        ? $shipment->trackingEvents
                            ->sortByDesc(fn ($event) => $event->occurred_at?->timestamp ?? $event->id)
                            ->take(20)
                            ->values()
                            ->map(fn ($event) => [
                                'id' => $event->id,
                                'status' => $event->status,
                                'status_id' => $event->status_id,
                                'location' => $event->location,
                                'source' => $event->source,
                                'occurred_at' => $event->occurred_at?->toIso8601String(),
                            ])
                            ->all()
                        : [],
                ];
            }),
            'fulfillment_events' => $this->whenLoaded('fulfillmentEvents', fn () => $this->fulfillmentEvents
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'source' => $event->source,
                    'event_type' => $event->event_type,
                    'previous_method' => $event->previous_method,
                    'new_method' => $event->new_method,
                    'provider_status' => $event->provider_status,
                    'reason' => $event->reason,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                    'created_at' => $event->created_at?->toIso8601String(),
                ])->values()->all()),
            'subtotal' => (float) $this->subtotal,
            'shipping' => (float) $this->shipping,
            'cod_fee' => (float) $this->cod_fee,
            'cgst' => (float) $this->cgst,
            'sgst' => (float) $this->sgst,
            'igst' => (float) $this->igst,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'seller_state' => $this->seller_state,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'address' => [
                'full_name' => $this->full_name,
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'city' => $this->city,
                'district' => $this->district,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
            ],
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'hsn' => $item->hsn,
                'slug' => $item->product_slug,
                'image' => $item->product_image,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'shipped_quantity' => (int) $item->shipped_quantity,
                'returned_quantity' => (int) $item->returned_quantity,
                'restocked_quantity' => (int) $item->restocked_quantity,
                'inventory_reservation' => $item->relationLoaded('inventoryReservation')
                    && $item->inventoryReservation ? [
                        'id' => $item->inventoryReservation->id,
                        'state' => $item->inventoryReservation->state->value,
                        'quantity' => (int) $item->inventoryReservation->quantity,
                        'expires_at' => $item->inventoryReservation->expires_at?->toIso8601String(),
                    ] : null,
                'weight_kg' => (float) $item->weight_kg,
                'length_cm' => (float) $item->length_cm,
                'breadth_cm' => (float) $item->breadth_cm,
                'height_cm' => (float) $item->height_cm,
                'line_total' => (float) $item->line_total,
            ])->values()->all()),
        ];
    }

    private function canSwitchToManual(): bool
    {
        if (
            $this->fulfillment_method !== FulfillmentMethod::Shiprocket
            || in_array($this->status, ['Shipped', 'Delivered', 'Cancelled'], true)
        ) {
            return false;
        }

        $shipment = $this->shiprocketShipment;

        return ! $shipment?->awb_code
            && ! $shipment?->pickup_scheduled_at;
    }
}
