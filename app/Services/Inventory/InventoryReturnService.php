<?php

namespace App\Services\Inventory;

use App\Exceptions\InvalidInventoryTransitionException;
use App\Models\InventoryReturn;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryReturnService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly InventoryOutbox $outbox,
    ) {}

    public function receive(
        OrderItem $orderItem,
        int $quantity,
        string $disposition,
        string $reason,
        string $idempotencyKey,
        ?int $actorId = null,
    ): InventoryReturn {
        if ($quantity < 1 || ! in_array($disposition, ['restock', 'damaged', 'inspection'], true)) {
            throw new InvalidInventoryTransitionException('Invalid return quantity or disposition.');
        }

        return DB::transaction(function () use (
            $orderItem,
            $quantity,
            $disposition,
            $reason,
            $idempotencyKey,
            $actorId,
        ): InventoryReturn {
            $existing = InventoryReturn::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $item = OrderItem::query()->lockForUpdate()->findOrFail($orderItem->id);
            $remaining = (int) $item->shipped_quantity - (int) $item->returned_quantity;
            if ($quantity > $remaining) {
                throw new InvalidInventoryTransitionException('Return quantity exceeds shipped, unreturned quantity.');
            }

            $return = InventoryReturn::query()->create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $item->order_id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'actor_id' => $actorId,
                'quantity' => $quantity,
                'disposition' => $disposition,
                'status' => $disposition === 'inspection' ? 'received' : 'processed',
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'processed_at' => $disposition === 'inspection' ? null : now(),
            ]);

            $item->increment('returned_quantity', $quantity);

            if ($disposition === 'restock') {
                $this->inventory->restockReturn(
                    $item->fresh(),
                    $quantity,
                    $idempotencyKey.':restock',
                    actorId: $actorId,
                    reason: $reason,
                );
            }

            $this->outbox->record(
                'inventory.return.received',
                'inventory_return',
                $return->id,
                $idempotencyKey.':event',
                [
                    'return_id' => $return->id,
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $quantity,
                    'disposition' => $disposition,
                ],
            );

            return $return->fresh();
        });
    }
}
