<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Enums\InventoryReservationState;
use App\Jobs\CancelShiprocketOrder;
use App\Jobs\SendOrderCancellationEmail;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Validation\ValidationException;

class OrderCancellationService
{
    private const CUSTOMER_CANCELLABLE_STATUSES = [
        'AwaitingPayment',
        'InventoryHold',
        'Processing',
        'Packed',
    ];

    private const ADMIN_CANCELLABLE_STATUSES = [
        'AwaitingPayment',
        'InventoryHold',
        'Processing',
        'Packed',
    ];

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    public function canCustomerCancel(Order $order): bool
    {
        $order->loadMissing('shiprocketShipment');

        if (! in_array($order->status, self::CUSTOMER_CANCELLABLE_STATUSES, true)) {
            return false;
        }

        if ($order->cancelled_at || $order->cancel_requested_at) {
            return false;
        }

        $shipment = $order->shiprocketShipment;

        return blank($shipment?->awb_code)
            && blank($shipment?->pickup_scheduled_at);
    }

    public function canAdminCancel(Order $order): bool
    {
        if ($order->status === 'Cancelled' || $order->cancelled_at || $order->cancel_requested_at) {
            return false;
        }

        return in_array($order->status, self::ADMIN_CANCELLABLE_STATUSES, true);
    }

    /**
     * @return array{order: Order, deferred: bool}
     */
    public function cancelByCustomer(Order $order, string $reason, ?User $actor = null): array
    {
        if (! $this->canCustomerCancel($order)) {
            throw ValidationException::withMessages([
                'order' => 'This order can no longer be cancelled.',
            ]);
        }

        return $this->cancel($order, $reason, 'customer', $actor?->id);
    }

    /**
     * @return array{order: Order, deferred: bool}
     */
    public function cancelByAdmin(Order $order, string $reason, ?User $actor = null): array
    {
        if (! $this->canAdminCancel($order)) {
            throw ValidationException::withMessages([
                'status' => "Order cannot move from {$order->status} to Cancelled.",
            ]);
        }

        return $this->cancel($order, $reason, 'admin', $actor?->id);
    }

    /**
     * Finalize a previously requested Shiprocket cancel after remote success.
     */
    public function finalizeAfterShiprocket(Order $order): Order
    {
        $order->loadMissing('items.inventoryReservation', 'shiprocketShipment');

        $this->releaseInventory($order, $order->cancellation_reason ?? 'Shiprocket cancellation confirmed', 'shiprocket-cancel');
        $this->applyCancelledState(
            $order,
            $order->cancellation_reason ?? 'Cancelled in Shiprocket',
            preserveRequestedAt: true,
        );

        SendOrderCancellationEmail::dispatch($order->id);

        return $order->fresh(['items', 'shiprocketShipment', 'user']);
    }

    public function markRefunded(Order $order): Order
    {
        if ($order->payment_status !== 'refund_pending') {
            throw ValidationException::withMessages([
                'payment_status' => 'Only refund-pending prepaid orders can be marked refunded.',
            ]);
        }

        $order->forceFill([
            'payment_status' => 'refunded',
        ])->save();

        return $order->fresh(['items', 'shiprocketShipment', 'user']);
    }

    /**
     * @return array{order: Order, deferred: bool}
     */
    private function cancel(Order $order, string $reason, string $source, ?int $actorId): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'A cancellation reason is required.',
            ]);
        }

        $order->loadMissing('items.inventoryReservation', 'shiprocketShipment');

        $needsRemoteCancel = $order->fulfillment_method === FulfillmentMethod::Shiprocket
            && filled($order->shiprocketShipment?->shiprocket_order_id)
            && blank($order->shiprocketShipment?->cancelled_at);

        if ($needsRemoteCancel) {
            $order->forceFill([
                'cancel_requested_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            CancelShiprocketOrder::dispatch($order->id);

            return [
                'order' => $order->fresh(['items', 'shiprocketShipment', 'user']),
                'deferred' => true,
            ];
        }

        $this->releaseInventory($order, $reason, $source === 'customer' ? 'customer-cancel' : 'cancel');
        $previousStatus = $order->status;
        $this->applyCancelledState($order, $reason);
        SendOrderCancellationEmail::dispatch($order->id);

        app(FulfillmentAuditService::class)->record(
            $order,
            'order_cancelled',
            $source,
            'order-cancel-'.$order->id.'-'.now()->timestamp,
            [
                'actor_user_id' => $actorId,
                'reason' => $reason,
                'previous_status' => $previousStatus,
                'new_status' => 'Cancelled',
                'metadata' => [
                    'payment_status' => $order->fresh()->payment_status,
                ],
            ],
        );

        return [
            'order' => $order->fresh(['items', 'shiprocketShipment', 'user']),
            'deferred' => false,
        ];
    }

    private function applyCancelledState(Order $order, string $reason, bool $preserveRequestedAt = false): void
    {
        $paymentStatus = $order->payment_status;
        if (
            $order->payment_method === 'razorpay'
            && $order->payment_status === 'paid'
        ) {
            $paymentStatus = 'refund_pending';
        }

        $order->forceFill([
            'status' => 'Cancelled',
            'cancel_requested_at' => $preserveRequestedAt
                ? ($order->cancel_requested_at ?? now())
                : now(),
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'payment_status' => $paymentStatus,
        ])->save();
    }

    private function releaseInventory(Order $order, string $reason, string $suffix): void
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
                "order:{$order->id}:item:{$item->id}:{$suffix}",
                correlationId: 'order:'.$order->id,
            );
        }
    }
}
