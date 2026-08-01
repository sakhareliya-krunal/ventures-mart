<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OrderTrackController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function show(string $number): JsonResponse
    {
        $order = $this->findByNumber($number);

        return response()->json([
            'data' => $this->trackPayload($order),
        ]);
    }

    public function invoice(string $number): Response
    {
        $order = $this->findByNumber($number);

        return $this->invoices->streamPdf($order);
    }

    private function findByNumber(string $number): Order
    {
        $order = Order::query()
            ->with('items')
            ->where('number', $number)
            ->first();

        abort_if(! $order, 404, 'Order not found.');

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function trackPayload(Order $order): array
    {
        $status = (string) $order->status;
        $timeline = $this->timeline($status);

        $returnEligible = $status === 'Delivered'
            && $order->updated_at
            && $order->updated_at->gte(now()->subDays(7));

        $hasCourier = filled($order->courier_partner)
            || filled($order->awb_number)
            || filled($order->tracking_number)
            || filled($order->dispatched_at)
            || filled($order->expected_delivery_at);

        return [
            'number' => $order->number,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'timeline' => $timeline,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'created_at' => $order->created_at?->toIso8601String(),
            'expected_delivery_at' => $order->expected_delivery_at?->toIso8601String(),
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'location' => [
                'city' => $order->city,
                'state' => $order->state,
            ],
            'courier' => [
                'partner' => $order->courier_partner,
                'awb_number' => $order->awb_number,
                'tracking_number' => $order->tracking_number,
                'dispatched_at' => $order->dispatched_at?->toIso8601String(),
                'expected_delivery_at' => $order->expected_delivery_at?->toIso8601String(),
                'has_details' => $hasCourier,
            ],
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'image' => $item->product_image,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'line_total' => (float) $item->line_total,
            ])->values()->all(),
            'totals' => [
                'subtotal' => (float) $order->subtotal,
                'shipping' => (float) $order->shipping,
                'cod_fee' => (float) $order->cod_fee,
                'cgst' => (float) $order->cgst,
                'sgst' => (float) $order->sgst,
                'igst' => (float) $order->igst,
                'tax' => (float) $order->tax,
                'total' => (float) $order->total,
            ],
            'invoice_available' => $this->invoices->isInvoiceable($order),
            'return_eligible' => $returnEligible,
            'support' => [
                'email' => config('invoice.email'),
                'phone' => config('invoice.phone'),
                'whatsapp' => 'https://wa.me/919173279323',
                'contact_path' => '/contact',
                'returns_path' => '/returns',
            ],
        ];
    }

    /**
     * @return array{confirmed: bool, packed: bool, shipped: bool, delivered: bool}
     */
    private function timeline(string $status): array
    {
        $rank = match ($status) {
            'Processing' => 1,
            'Packed' => 2,
            'Shipped' => 3,
            'Delivered' => 4,
            default => 0,
        };

        return [
            'confirmed' => $rank >= 1,
            'packed' => $rank >= 2,
            'shipped' => $rank >= 3,
            'delivered' => $rank >= 4,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'AwaitingPayment' => 'Awaiting payment',
            'Processing' => 'Confirmed',
            'Packed' => 'Packed',
            'Shipped' => 'Shipped',
            'Delivered' => 'Delivered',
            'Cancelled' => 'Cancelled',
            default => $status,
        };
    }
}
