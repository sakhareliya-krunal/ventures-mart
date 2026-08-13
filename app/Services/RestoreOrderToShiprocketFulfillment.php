<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Jobs\FulfillShiprocketOrder;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RestoreOrderToShiprocketFulfillment
{
    public function __construct(
        private readonly OrderFulfillmentLock $lock,
        private readonly FulfillmentAuditService $audit,
    ) {}

    public function handle(Order $order, int $actorUserId, ?string $reason = null): Order
    {
        return $this->lock->run($order->id, function () use ($order, $actorUserId, $reason): Order {
            $order = Order::query()
                ->with('shiprocketShipment')
                ->findOrFail($order->id);

            if ($order->fulfillment_method === FulfillmentMethod::Shiprocket) {
                return $order;
            }

            $shipment = $order->shiprocketShipment;
            if (
                in_array($order->status, ['Cancelled', 'Shipped', 'Delivered'], true)
                || ! $shipment
                || ! $shipment->shiprocket_order_id
                || ! $shipment->shipment_id
            ) {
                throw ValidationException::withMessages([
                    'fulfillment_method' => 'This order cannot restore Shiprocket fulfillment. A prior Shiprocket order and shipment ID are required, and the order must not be cancelled, shipped, or delivered.',
                ]);
            }

            $attemptId = (string) Str::uuid();
            $reason = trim((string) $reason) ?: 'Admin restored fulfillment to Shiprocket';

            $restored = DB::transaction(function () use (
                $order,
                $shipment,
                $actorUserId,
                $reason,
                $attemptId,
            ): Order {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                if ($locked->fulfillment_method !== FulfillmentMethod::Manual) {
                    return $locked->load('shiprocketShipment');
                }

                $locked->forceFill(['fulfillment_method' => FulfillmentMethod::Shiprocket])->save();

                $shipment->forceFill([
                    'sync_status' => 'pending',
                    'stage' => $shipment->awb_code
                        ? ($shipment->pickup_scheduled_at ? 'pickup_scheduled' : 'awb_assigned')
                        : 'order_created',
                    'cancelled_at' => null,
                    'last_error' => null,
                ])->save();

                $this->audit->record(
                    $locked,
                    'shiprocket_restore_completed',
                    'admin',
                    "order:{$locked->id}:shiprocket-restore-completed:{$attemptId}",
                    [
                        'shipment' => $shipment,
                        'actor_user_id' => $actorUserId,
                        'previous_method' => FulfillmentMethod::Manual,
                        'new_method' => FulfillmentMethod::Shiprocket,
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

            FulfillShiprocketOrder::dispatch($restored->id);

            return $restored;
        });
    }
}
