<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\OrderCancellationService;
use App\Services\OrderReplacementService;
use App\Services\OrderShipmentDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderTrackController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly OrderShipmentDetails $shipments,
    ) {}

    public function show(Request $request, string $number): JsonResponse
    {
        $order = $this->findByNumber($number);
        $this->authorizeOrder($request, $order);

        return response()->json([
            'data' => $this->trackPayload($order),
        ]);
    }

    public function invoice(Request $request, string $number): Response
    {
        $order = $this->findByNumber($number);
        $this->authorizeOrder($request, $order);

        return $this->invoices->streamPdf($order);
    }

    private function findByNumber(string $number): Order
    {
        $order = Order::query()
            ->with(['items', 'shiprocketShipment'])
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
        $cancellations = app(OrderCancellationService::class);
        $replacementService = app(OrderReplacementService::class);

        $shipment = $this->shipments->forCustomer($order);
        $replacementRequests = $replacementService->forOrder($order);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'invoice_number' => $order->invoice_number,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'timeline' => $timeline,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'created_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'invoice_issued_at' => $order->invoice_issued_at?->toIso8601String(),
            'expected_delivery_at' => $order->expected_delivery_at?->toIso8601String(),
            'dispatched_at' => $order->dispatched_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            'cancel_requested_at' => $order->cancel_requested_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $order->cancellation_reason,
            'can_cancel' => $cancellations->canCustomerCancel($order),
            'can_request_replacement' => $replacementService->canRequest($order),
            'replacement_requests' => $replacementRequests,
            'customer' => [
                'full_name' => $order->full_name,
                'email' => $order->email,
                'phone' => $order->phone,
            ],
            'address' => [
                'address' => $order->address,
                'city' => $order->city,
                'district' => $order->district,
                'state' => $order->state,
                'postal_code' => $order->postal_code,
            ],
            'location' => [
                'city' => $order->city,
                'district' => $order->district,
                'state' => $order->state,
            ],
            'shipment' => $shipment,
            'courier' => $shipment,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'hsn' => $item->hsn,
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
            'support' => [
                'email' => config('invoice.email'),
                'phone' => config('invoice.phone'),
                'whatsapp' => 'https://wa.me/919173279323',
                'contact_path' => '/contact',
                'replacement_path' => '/replacement',
            ],
        ];
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless(
            (bool) $user->is_admin
            || $order->user_id === $user->id
            || ($order->user_id === null && $order->email === $user->email),
            403,
        );
    }

    /**
     * @return array{confirmed: bool, packed: bool, shipped: bool, delivered: bool}
     */
    private function timeline(string $status): array
    {
        $rank = match ($status) {
            'Processing', 'InventoryHold' => 1,
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
            'InventoryHold' => 'Inventory review',
            'Processing' => 'Confirmed',
            'Packed' => 'Packed',
            'Shipped' => 'Shipped',
            'Delivered' => 'Delivered',
            'Cancelled' => 'Cancelled',
            default => $status,
        };
    }
}
