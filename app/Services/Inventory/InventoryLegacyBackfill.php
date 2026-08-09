<?php

namespace App\Services\Inventory;

use App\Enums\InventoryLedgerType;
use App\Enums\InventoryReservationState;
use App\Enums\OrderInventoryStatus;
use App\Models\InventoryAuditFlag;
use App\Models\InventoryBalance;
use App\Models\InventoryLedgerEntry;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShiprocketShipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryLegacyBackfill
{
    /** @var list<string> */
    private array $activeOrderStatuses = ['Processing', 'Packed', 'AwaitingPayment'];

    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    /**
     * @return array{
     *   products_processed: int,
     *   flags_created: int,
     *   availability_preserved: bool
     * }
     */
    public function run(): array
    {
        $flagsCreated = 0;
        $productsProcessed = 0;
        $availabilityPreserved = true;

        Product::query()->orderBy('id')->chunkById(100, function ($products) use (&$flagsCreated, &$productsProcessed, &$availabilityPreserved): void {
            foreach ($products as $product) {
                DB::transaction(function () use ($product, &$flagsCreated, &$productsProcessed, &$availabilityPreserved): void {
                    $legacyAvailable = (int) $product->stock;
                    $activeCommitted = $this->activeCommittedQuantity($product->id, $flagsCreated);
                    $onHand = $legacyAvailable + $activeCommitted;

                    $balance = InventoryBalance::query()->updateOrCreate(
                        ['product_id' => $product->id],
                        [
                            'on_hand' => $onHand,
                            'reserved' => 0,
                            'committed' => $activeCommitted,
                            'version' => 0,
                            'low_stock_threshold' => $product->low_stock_threshold
                                ?? config('inventory.default_low_stock_threshold'),
                            'reorder_point' => $product->reorder_point
                                ?? config('inventory.default_reorder_point'),
                        ],
                    );

                    $this->createOpeningLedger($balance, $onHand, $activeCommitted);

                    $this->backfillActiveReservations($product->id, $flagsCreated);

                    $this->flagAmbiguousOrders($product->id, $flagsCreated);

                    $this->inventory->syncProductStock($product->fresh(), $balance->fresh());

                    if ((int) $product->fresh()->stock !== $legacyAvailable) {
                        $availabilityPreserved = false;
                    }

                    $productsProcessed++;
                });
            }
        });

        return [
            'products_processed' => $productsProcessed,
            'flags_created' => $flagsCreated,
            'availability_preserved' => $availabilityPreserved,
        ];
    }

    private function activeCommittedQuantity(int $productId, int &$flagsCreated): int
    {
        $total = 0;

        OrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', function ($query): void {
                $query->whereIn('status', ['Processing', 'Packed'])
                    ->where('status', '!=', 'Cancelled')
                    ->where(function ($paymentQuery): void {
                        $paymentQuery
                            ->where('payment_method', 'cod')
                            ->orWhere('payment_status', 'paid');
                    });
            })
            ->with(['order.shiprocketShipment'])
            ->each(function (OrderItem $item) use (&$total, &$flagsCreated): void {
                $order = $item->order;

                if ($this->isShiprocketShipped($order)) {
                    $this->flag(
                        productId: $item->product_id,
                        orderId: $order->id,
                        code: 'local_processing_shiprocket_shipped',
                        message: 'Order is Processing/Packed locally but Shiprocket indicates shipment progress; excluded from committed backfill.',
                        context: [
                            'order_item_id' => $item->id,
                            'local_status' => $order->status,
                            'shiprocket_status' => $order->shiprocketShipment?->shipment_status,
                        ],
                        flagsCreated: $flagsCreated,
                    );

                    return;
                }

                $total += (int) $item->quantity;
            });

        return $total;
    }

    private function backfillActiveReservations(int $productId, int &$flagsCreated): void
    {
        OrderItem::query()
            ->where('product_id', $productId)
            ->whereHas('order', function ($query): void {
                $query->whereIn('status', ['Processing', 'Packed'])
                    ->where('status', '!=', 'Cancelled')
                    ->where(function ($paymentQuery): void {
                        $paymentQuery
                            ->where('payment_method', 'cod')
                            ->orWhere('payment_status', 'paid');
                    });
            })
            ->with('order.shiprocketShipment')
            ->each(function (OrderItem $item) use (&$flagsCreated): void {
                if ($this->isShiprocketShipped($item->order)) {
                    return;
                }

                InventoryReservation::query()->updateOrCreate(
                    ['order_item_id' => $item->id],
                    [
                        'order_id' => $item->order_id,
                        'product_id' => $item->product_id,
                        'quantity' => (int) $item->quantity,
                        'state' => InventoryReservationState::Committed,
                        'committed_at' => $item->order->paid_at ?? $item->order->created_at,
                    ],
                );

                InventoryLedgerEntry::query()->firstOrCreate(
                    ['idempotency_key' => "legacy:commit:order-item:{$item->id}"],
                    [
                        'uuid' => (string) Str::uuid(),
                        'product_id' => $item->product_id,
                        'order_id' => $item->order_id,
                        'order_item_id' => $item->id,
                        'type' => InventoryLedgerType::OrderCommitted,
                        'on_hand_delta' => 0,
                        'reserved_delta' => 0,
                        'committed_delta' => (int) $item->quantity,
                        'on_hand_balance' => InventoryBalance::query()->where('product_id', $item->product_id)->value('on_hand'),
                        'reserved_balance' => 0,
                        'committed_balance' => InventoryBalance::query()->where('product_id', $item->product_id)->value('committed'),
                        'reason' => 'Legacy active order commitment',
                        'metadata' => ['legacy_backfill' => true],
                        'occurred_at' => $item->order->created_at ?? now(),
                    ],
                );

                $item->order->forceFill([
                    'inventory_status' => OrderInventoryStatus::Committed,
                ])->save();
            });
    }

    private function flagAmbiguousOrders(int $productId, int &$flagsCreated): void
    {
        Order::query()
            ->where('status', 'Cancelled')
            ->whereHas('items', fn ($query) => $query->where('product_id', $productId))
            ->each(function (Order $order) use ($productId, &$flagsCreated): void {
                $this->flag(
                    productId: $productId,
                    orderId: $order->id,
                    code: 'cancelled_legacy_stock_unknown',
                    message: 'Cancelled legacy order may require manual stock review; no automatic inventory adjustment applied.',
                    context: ['status' => $order->status, 'payment_status' => $order->payment_status],
                    flagsCreated: $flagsCreated,
                );
            });

        Order::query()
            ->where('status', 'AwaitingPayment')
            ->where('payment_method', '!=', 'cod')
            ->whereHas('items', fn ($query) => $query->where('product_id', $productId))
            ->each(function (Order $order) use ($productId, &$flagsCreated): void {
                $this->flag(
                    productId: $productId,
                    orderId: $order->id,
                    code: 'awaiting_payment_not_reserved',
                    message: 'Legacy awaiting-payment order was not auto-reserved to preserve current storefront availability.',
                    context: [
                        'created_at' => $order->created_at?->toIso8601String(),
                        'payment_status' => $order->payment_status,
                    ],
                    flagsCreated: $flagsCreated,
                );

                $order->forceFill([
                    'inventory_status' => OrderInventoryStatus::Unallocated,
                ])->save();
            });
    }

    private function createOpeningLedger(InventoryBalance $balance, int $onHand, int $committed): void
    {
        InventoryLedgerEntry::query()->firstOrCreate(
            ['idempotency_key' => "legacy:opening:product:{$balance->product_id}"],
            [
                'uuid' => (string) Str::uuid(),
                'product_id' => $balance->product_id,
                'type' => InventoryLedgerType::OpeningBalance,
                'on_hand_delta' => $onHand,
                'reserved_delta' => 0,
                'committed_delta' => 0,
                'on_hand_balance' => $onHand,
                'reserved_balance' => 0,
                'committed_balance' => 0,
                'reason' => 'Legacy stock backfill opening balance',
                'metadata' => ['legacy_backfill' => true],
                'occurred_at' => Carbon::now(),
            ],
        );
    }

    private function isShiprocketShipped(Order $order): bool
    {
        $shipment = $order->shiprocketShipment;

        if (! $shipment) {
            return false;
        }

        $status = strtoupper((string) $shipment->shipment_status);

        return in_array($status, [
            'SHIPPED',
            'IN TRANSIT',
            'IN_TRANSIT',
            'OUT FOR DELIVERY',
            'OUT_FOR_DELIVERY',
            'DELIVERED',
            'PICKED UP',
            'PICKED_UP',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function flag(
        ?int $productId,
        ?int $orderId,
        string $code,
        string $message,
        array $context,
        int &$flagsCreated,
    ): void {
        InventoryAuditFlag::query()->firstOrCreate(
            [
                'product_id' => $productId,
                'order_id' => $orderId,
                'code' => $code,
            ],
            [
                'message' => $message,
                'context' => $context,
            ],
        );

        $flagsCreated++;
    }
}
