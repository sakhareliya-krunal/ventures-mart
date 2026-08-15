<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\OrderCancellationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
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
            'fulfillment_method' => $this->fulfillment_method?->value,
            'order_type' => $this->order_type ?: 'standard',
            'parent_order_id' => $this->parent_order_id,
            'can_cancel' => app(OrderCancellationService::class)->canCustomerCancel($this->resource),
            'cancel_requested_at' => $this->cancel_requested_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'payment_status' => $this->payment_status,
            'payment_method' => $this->payment_method,
            'meta_purchase_event_id' => $this->meta_purchase_event_id,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'subtotal' => (float) $this->subtotal,
            'shipping' => (float) $this->shipping,
            'cod_fee' => (float) $this->cod_fee,
            'cgst' => (float) $this->cgst,
            'sgst' => (float) $this->sgst,
            'igst' => (float) $this->igst,
            'tax' => (float) $this->tax,
            'total' => (float) $this->total,
            'seller_state' => $this->seller_state,
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
                'product_id' => $item->product_id,
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'hsn' => $item->hsn,
                'slug' => $item->product_slug,
                'image' => $item->product_image,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'line_total' => (float) $item->line_total,
            ])),
        ];
    }
}
