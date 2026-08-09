<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SwitchOrderToManualFulfillment
{
    public function __construct(
        private readonly OrderFulfillmentLock $lock,
        private readonly ShiprocketService $shiprocket,
        private readonly FulfillmentAuditService $audit,
    ) {}

    public function handle(Order $order, int $actorUserId, ?string $reason = null): Order
    {
        return $this->lock->run($order->id, function () use ($order, $actorUserId, $reason): Order {
            $order = Order::query()
                ->with('shiprocketShipment')
                ->findOrFail($order->id);

            if ($order->fulfillment_method === FulfillmentMethod::Manual) {
                return $order;
            }

            $shipment = $order->shiprocketShipment;
            if (
                in_array($order->status, ['Shipped', 'Delivered', 'Cancelled'], true)
                || filled($shipment?->awb_code)
                || filled($order->awb_number)
                || $shipment?->pickup_scheduled_at
            ) {
                throw ValidationException::withMessages([
                    'fulfillment_method' => 'This order can no longer switch to manual fulfillment because courier handoff or AWB assignment has started.',
                ]);
            }

            $attemptId = (string) Str::uuid();
            $reason = trim((string) $reason) ?: 'Admin switched fulfillment to manual';
            $this->audit->record(
                $order,
                'manual_switch_requested',
                'admin',
                "order:{$order->id}:manual-switch-request:{$attemptId}",
                [
                    'shipment' => $shipment,
                    'actor_user_id' => $actorUserId,
                    'previous_method' => FulfillmentMethod::Shiprocket,
                    'new_method' => FulfillmentMethod::Manual,
                    'reason' => $reason,
                ],
            );

            try {
                if ($shipment?->shiprocket_order_id) {
                    $this->shiprocket->cancelOrder((int) $shipment->shiprocket_order_id);
                }
            } catch (Throwable $exception) {
                $this->audit->record(
                    $order,
                    'manual_switch_failed',
                    'admin',
                    "order:{$order->id}:manual-switch-failed:{$attemptId}",
                    [
                        'shipment' => $shipment,
                        'actor_user_id' => $actorUserId,
                        'previous_method' => FulfillmentMethod::Shiprocket,
                        'new_method' => FulfillmentMethod::Manual,
                        'reason' => $exception->getMessage(),
                    ],
                );

                throw ValidationException::withMessages([
                    'fulfillment_method' => 'Shiprocket could not confirm cancellation. The order remains Shiprocket-managed.',
                ]);
            }

            return DB::transaction(function () use (
                $order,
                $shipment,
                $actorUserId,
                $reason,
                $attemptId,
            ): Order {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->fulfillment_method !== FulfillmentMethod::Shiprocket) {
                    return $locked->load('shiprocketShipment');
                }

                $locked->forceFill(['fulfillment_method' => FulfillmentMethod::Manual])->save();

                if ($shipment) {
                    $shipment->forceFill([
                        'sync_status' => 'cancelled',
                        'stage' => 'switched_to_manual',
                        'shipment_status' => 'Cancelled before AWB',
                        'cancelled_at' => now(),
                        'last_error' => null,
                    ])->save();
                }

                $this->audit->record(
                    $locked,
                    'manual_switch_completed',
                    'admin',
                    "order:{$locked->id}:manual-switch-completed:{$attemptId}",
                    [
                        'shipment' => $shipment,
                        'actor_user_id' => $actorUserId,
                        'previous_method' => FulfillmentMethod::Shiprocket,
                        'new_method' => FulfillmentMethod::Manual,
                        'reason' => $reason,
                    ],
                );

                return $locked->load([
                    'items.inventoryReservation',
                    'user',
                    'shiprocketShipment',
                    'fulfillmentEvents' => fn ($query) => $query->latest()->limit(50),
                ]);
            });
        });
    }
}
