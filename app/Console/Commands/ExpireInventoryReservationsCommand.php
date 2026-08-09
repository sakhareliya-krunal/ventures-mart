<?php

namespace App\Console\Commands;

use App\Enums\InventoryReservationState;
use App\Models\InventoryReservation;
use App\Services\Inventory\InventoryService;
use Illuminate\Console\Command;

class ExpireInventoryReservationsCommand extends Command
{
    protected $signature = 'inventory:expire-reservations {--limit=500}';

    protected $description = 'Release expired unpaid payment reservations';

    public function handle(InventoryService $inventory): int
    {
        $expired = 0;

        InventoryReservation::query()
            ->where('state', InventoryReservationState::Reserved->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with('orderItem.order')
            ->limit(max(1, (int) $this->option('limit')))
            ->get()
            ->each(function (InventoryReservation $reservation) use ($inventory, &$expired): void {
                $order = $reservation->orderItem->order;
                if ($order->payment_status === 'paid') {
                    return;
                }

                $inventory->expire(
                    $reservation->orderItem,
                    "order:{$reservation->order_id}:item:{$reservation->order_item_id}:expire",
                    correlationId: 'order:'.$reservation->order_id,
                );
                if (! $order->inventoryReservations()->where('state', InventoryReservationState::Reserved->value)->exists()) {
                    $order->forceFill([
                        'payment_status' => 'failed',
                        'status' => 'Cancelled',
                        'cancelled_at' => now(),
                        'cancellation_reason' => 'Payment reservation expired',
                    ])->save();
                }
                $expired++;
            });

        $this->info("Expired {$expired} reservation(s).");

        return self::SUCCESS;
    }
}
