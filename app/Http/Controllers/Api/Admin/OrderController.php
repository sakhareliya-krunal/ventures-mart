<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\InventoryReservationState;
use App\Enums\OrderInventoryStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminOrderResource;
use App\Jobs\CancelShiprocketOrder;
use App\Jobs\FulfillShiprocketOrder;
use App\Jobs\SyncShiprocketTracking;
use App\Models\Order;
use App\Services\Inventory\InventoryService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InventoryService $inventory,
    ) {}

    private const STATUSES = ['AwaitingPayment', 'InventoryHold', 'Processing', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];

    private const STATUS_TRANSITIONS = [
        'AwaitingPayment' => ['Processing', 'Cancelled'],
        'InventoryHold' => ['Processing', 'Cancelled'],
        'Processing' => ['Packed', 'Shipped', 'Delivered', 'Cancelled'],
        'Packed' => ['Shipped', 'Delivered', 'Cancelled'],
        'Shipped' => ['Delivered'],
        'Delivered' => [],
        'Cancelled' => [],
    ];

    private const COURIER_FIELDS = [
        'courier_partner',
        'awb_number',
        'tracking_number',
        'dispatched_at',
        'expected_delivery_at',
    ];

    public function index(Request $request)
    {
        $query = Order::query()->with(['items.inventoryReservation', 'user', 'shiprocketShipment'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return AdminOrderResource::collection(
            $query->paginate(min((int) $request->integer('per_page', 20), 100))
        );
    }

    public function show(Order $order)
    {
        $order->load(['items.inventoryReservation', 'user', 'shiprocketShipment']);

        return new AdminOrderResource($order);
    }

    public function invoice(Order $order)
    {
        return $this->invoices->streamPdf($order);
    }

    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'required', 'string', Rule::in(self::STATUSES)],
            'payment_status' => ['sometimes', 'required', 'string', Rule::in(['paid'])],
            'courier_partner' => ['sometimes', 'nullable', 'string', 'max:120'],
            'awb_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tracking_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dispatched_at' => ['sometimes', 'nullable', 'date'],
            'expected_delivery_at' => ['sometimes', 'nullable', 'date'],
            'cancellation_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        if ($validated === []) {
            return response()->json([
                'message' => 'Nothing to update.',
            ], 422);
        }

        $order->loadMissing('shiprocketShipment');
        if (
            $order->shiprocketShipment
            && collect(self::COURIER_FIELDS)->contains(fn ($field) => array_key_exists($field, $validated))
        ) {
            return response()->json([
                'message' => 'Courier fields are managed by Shiprocket for this order.',
            ], 422);
        }

        if (array_key_exists('payment_status', $validated) && $validated['payment_status'] === 'paid') {
            if ($order->payment_method !== 'cod') {
                return response()->json([
                    'message' => 'Only COD orders can be marked paid manually.',
                ], 422);
            }

            if ($order->payment_status !== 'paid') {
                $order->forceFill([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);
            }
        }

        if (array_key_exists('status', $validated)) {
            if (
                $validated['status'] !== $order->status
                && ! in_array($validated['status'], self::STATUS_TRANSITIONS[$order->status] ?? [], true)
            ) {
                return response()->json([
                    'message' => "Order cannot move from {$order->status} to {$validated['status']}.",
                ], 422);
            }

            if (
                $validated['status'] === 'Cancelled'
                && $order->shiprocketShipment
                && ! $order->shiprocketShipment->cancelled_at
            ) {
                $order->forceFill([
                    'cancel_requested_at' => now(),
                    'cancellation_reason' => $validated['cancellation_reason'] ?? 'Admin cancellation',
                ])->save();
                CancelShiprocketOrder::dispatch($order->id);
                $order->load(['items.inventoryReservation', 'user', 'shiprocketShipment']);

                return new AdminOrderResource($order);
            }

            $order->status = $validated['status'];

            if ($order->getOriginal('status') === 'InventoryHold' && $validated['status'] === 'Processing') {
                $this->reacquireExceptionInventory($order);
            }

            if ($validated['status'] === 'Cancelled') {
                $this->releaseOrderInventory($order, $validated['cancellation_reason'] ?? 'Admin cancellation');
                $order->cancel_requested_at ??= now();
                $order->cancelled_at ??= now();
                $order->cancellation_reason = $validated['cancellation_reason'] ?? 'Admin cancellation';
            }

            if (in_array($validated['status'], ['Shipped', 'Delivered'], true)) {
                $this->consumeOrderInventory($order);
                $order->dispatched_at ??= now();
            }

            if (
                $validated['status'] === 'Delivered'
                && $order->payment_method === 'cod'
                && $order->payment_status === 'pending'
            ) {
                $order->payment_status = 'paid';
                $order->paid_at = now();
            }
        }

        foreach (self::COURIER_FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $order->{$field} = $validated[$field];
            }
        }

        $order->save();
        $order->load(['items.inventoryReservation', 'user', 'shiprocketShipment']);

        return new AdminOrderResource($order);
    }

    public function retryShiprocket(Order $order)
    {
        if (! config('services.shiprocket.enabled')) {
            return response()->json(['message' => 'Shiprocket integration is disabled.'], 422);
        }

        if ($order->status === 'Cancelled') {
            return response()->json(['message' => 'Cancelled orders cannot be fulfilled.'], 422);
        }

        FulfillShiprocketOrder::dispatch($order->id);

        return response()->json(['message' => 'Shiprocket fulfillment was queued.'], 202);
    }

    public function syncShiprocket(Order $order)
    {
        if (! $order->shiprocketShipment?->awb_code) {
            return response()->json(['message' => 'This order does not have a Shiprocket AWB.'], 422);
        }

        SyncShiprocketTracking::dispatch($order->id);

        return response()->json(['message' => 'Shiprocket tracking sync was queued.'], 202);
    }

    public function destroy(Order $order)
    {
        return response()->json([
            'message' => 'Orders are retained for inventory and financial audit. Cancel the order instead.',
        ], 422);
    }

    private function releaseOrderInventory(Order $order, string $reason): void
    {
        $order->loadMissing('items.inventoryReservation');

        foreach ($order->items as $item) {
            if (! in_array($item->inventoryReservation?->state, [
                InventoryReservationState::Reserved,
                InventoryReservationState::Committed,
            ], true)) {
                continue;
            }

            $this->inventory->release(
                $item,
                $reason,
                "order:{$order->id}:item:{$item->id}:cancel",
                correlationId: 'order:'.$order->id,
            );
        }
    }

    private function consumeOrderInventory(Order $order): void
    {
        $order->loadMissing('items.inventoryReservation');

        foreach ($order->items as $item) {
            if ($item->inventoryReservation?->state !== InventoryReservationState::Committed) {
                continue;
            }

            $this->inventory->consume(
                $item,
                "order:{$order->id}:item:{$item->id}:admin-handoff",
                correlationId: 'order:'.$order->id,
                reason: 'Admin confirmed courier handoff',
            );
        }
    }

    private function reacquireExceptionInventory(Order $order): void
    {
        $order->loadMissing('items.inventoryReservation');

        foreach ($order->items as $item) {
            if ($item->inventoryReservation?->state === InventoryReservationState::Committed) {
                continue;
            }

            $this->inventory->commit(
                $item,
                "order:{$order->id}:item:{$item->id}:inventory-hold-recovery",
                fromReserved: true,
                correlationId: 'order:'.$order->id,
                reason: 'Admin resolved paid inventory hold',
            );
        }

        $order->inventory_status = OrderInventoryStatus::Committed;
    }
}
