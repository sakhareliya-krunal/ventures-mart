<?php

namespace App\Services\Inventory;

use App\Enums\InventoryLedgerType;
use App\Enums\InventoryReservationState;
use App\Enums\OrderInventoryStatus;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\InvalidInventoryTransitionException;
use App\Exceptions\InventoryVersionConflictException;
use App\Models\InventoryBalance;
use App\Models\InventoryLedgerEntry;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    public function __construct(private readonly InventoryOutbox $outbox) {}

    /**
     * @return array{reservation: InventoryReservation, ledger: InventoryLedgerEntry}
     */
    public function reserve(
        OrderItem $orderItem,
        CarbonInterface $expiresAt,
        string $idempotencyKey,
        ?string $correlationId = null,
        ?string $reason = null,
    ): array {
        return $this->mutate(
            productIds: [$orderItem->product_id],
            idempotencyKey: $idempotencyKey,
            apply: function (Collection $balances) use ($orderItem, $expiresAt, $correlationId, $reason, $idempotencyKey): array {
                $balance = $balances->get($orderItem->product_id);
                $quantity = (int) $orderItem->quantity;

                if ($balance->available() < $quantity) {
                    throw new InsufficientInventoryException(
                        "Insufficient available stock for product {$orderItem->product_id}."
                    );
                }

                $reservation = InventoryReservation::query()->firstOrNew([
                    'order_item_id' => $orderItem->id,
                ]);

                if ($reservation->exists && $reservation->state !== InventoryReservationState::Reserved) {
                    if ($reservation->state === InventoryReservationState::Committed) {
                        return $this->existingReservationResult($orderItem, $idempotencyKey);
                    }

                    throw new InvalidInventoryTransitionException(
                        "Cannot reserve order item {$orderItem->id} in state {$reservation->state->value}."
                    );
                }

                if (! $reservation->exists) {
                    $balance->reserved += $quantity;
                    $balance->version++;

                    $reservation->fill([
                        'order_id' => $orderItem->order_id,
                        'product_id' => $orderItem->product_id,
                        'quantity' => $quantity,
                        'state' => InventoryReservationState::Reserved,
                        'expires_at' => $expiresAt,
                    ])->save();
                }

                $ledger = $this->recordLedger(
                    balance: $balance,
                    type: InventoryLedgerType::RazorpayReserved,
                    deltas: ['reserved' => $quantity],
                    idempotencyKey: $idempotencyKey,
                    orderId: $orderItem->order_id,
                    orderItemId: $orderItem->id,
                    correlationId: $correlationId,
                    reason: $reason ?? 'Razorpay checkout reservation',
                );

                $this->syncOrderInventoryStatus($orderItem->order_id);

                return ['reservation' => $reservation->fresh(), 'ledger' => $ledger];
            },
            orderItemId: $orderItem->id,
        );
    }

    /**
     * @return array{reservation: InventoryReservation, ledger: InventoryLedgerEntry}
     */
    public function commit(
        OrderItem $orderItem,
        string $idempotencyKey,
        bool $fromReserved = true,
        ?string $correlationId = null,
        ?string $reason = null,
    ): array {
        return $this->mutate(
            productIds: [$orderItem->product_id],
            idempotencyKey: $idempotencyKey,
            apply: function (Collection $balances) use ($orderItem, $fromReserved, $correlationId, $reason, $idempotencyKey): array {
                $balance = $balances->get($orderItem->product_id);
                $quantity = (int) $orderItem->quantity;
                $reservation = InventoryReservation::query()->where('order_item_id', $orderItem->id)->first();

                if ($reservation && $reservation->state === InventoryReservationState::Committed) {
                    return $this->existingReservationResult($orderItem, $idempotencyKey);
                }

                if ($fromReserved) {
                    if (! $reservation || $reservation->state === InventoryReservationState::Expired
                        || $reservation->state === InventoryReservationState::Released) {
                        if ($balance->available() < $quantity) {
                            throw new InsufficientInventoryException(
                                "Cannot recommit expired reservation for order item {$orderItem->id}."
                            );
                        }

                        $balance->committed += $quantity;
                        $balance->version++;

                        $reservation = $this->upsertReservation($orderItem, [
                            'state' => InventoryReservationState::Committed,
                            'committed_at' => now(),
                            'expires_at' => null,
                        ]);

                        $ledger = $this->recordLedger(
                            balance: $balance,
                            type: InventoryLedgerType::OrderCommitted,
                            deltas: ['committed' => $quantity],
                            idempotencyKey: $idempotencyKey,
                            orderId: $orderItem->order_id,
                            orderItemId: $orderItem->id,
                            correlationId: $correlationId,
                            reason: $reason ?? 'Late payment recommit',
                            metadata: ['reacquired' => true],
                        );
                    } else {
                        if ($reservation->state !== InventoryReservationState::Reserved) {
                            throw new InvalidInventoryTransitionException(
                                "Cannot commit order item {$orderItem->id} from state {$reservation->state->value}."
                            );
                        }

                        $balance->reserved -= $quantity;
                        $balance->committed += $quantity;
                        $balance->version++;

                        $reservation->update([
                            'state' => InventoryReservationState::Committed,
                            'committed_at' => now(),
                            'expires_at' => null,
                        ]);

                        $ledger = $this->recordLedger(
                            balance: $balance,
                            type: InventoryLedgerType::OrderCommitted,
                            deltas: ['reserved' => -$quantity, 'committed' => $quantity],
                            idempotencyKey: $idempotencyKey,
                            orderId: $orderItem->order_id,
                            orderItemId: $orderItem->id,
                            correlationId: $correlationId,
                            reason: $reason ?? 'Payment confirmed',
                        );
                    }
                } else {
                    if ($balance->available() < $quantity) {
                        throw new InsufficientInventoryException(
                            "Insufficient available stock to commit order item {$orderItem->id}."
                        );
                    }

                    $balance->committed += $quantity;
                    $balance->version++;

                    $reservation = $this->upsertReservation($orderItem, [
                        'state' => InventoryReservationState::Committed,
                        'committed_at' => now(),
                    ]);

                    $ledger = $this->recordLedger(
                        balance: $balance,
                        type: InventoryLedgerType::OrderCommitted,
                        deltas: ['committed' => $quantity],
                        idempotencyKey: $idempotencyKey,
                        orderId: $orderItem->order_id,
                        orderItemId: $orderItem->id,
                        correlationId: $correlationId,
                        reason: $reason ?? 'COD order commitment',
                    );
                }

                $this->syncOrderInventoryStatus($orderItem->order_id);

                return ['reservation' => $reservation->fresh(), 'ledger' => $ledger];
            },
            orderItemId: $orderItem->id,
        );
    }

    /**
     * @return array{reservation: InventoryReservation, ledger: InventoryLedgerEntry}
     */
    public function release(
        OrderItem $orderItem,
        string $reason,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): array {
        return $this->mutate(
            productIds: [$orderItem->product_id],
            idempotencyKey: $idempotencyKey,
            apply: function (Collection $balances) use ($orderItem, $reason, $correlationId, $idempotencyKey): array {
                $balance = $balances->get($orderItem->product_id);
                $quantity = (int) $orderItem->quantity;
                $reservation = InventoryReservation::query()->where('order_item_id', $orderItem->id)->first();

                if (! $reservation) {
                    throw new InvalidInventoryTransitionException(
                        "No reservation exists for order item {$orderItem->id}."
                    );
                }

                if (in_array($reservation->state, [
                    InventoryReservationState::Released,
                    InventoryReservationState::Expired,
                    InventoryReservationState::Consumed,
                ], true)) {
                    return $this->existingReservationResult($orderItem, $idempotencyKey);
                }

                $deltas = [];
                $type = InventoryLedgerType::ReservationReleased;

                if ($reservation->state === InventoryReservationState::Reserved) {
                    $balance->reserved -= $quantity;
                    $deltas['reserved'] = -$quantity;
                } elseif ($reservation->state === InventoryReservationState::Committed) {
                    $balance->committed -= $quantity;
                    $deltas['committed'] = -$quantity;
                    $type = InventoryLedgerType::CancellationReleased;
                } else {
                    throw new InvalidInventoryTransitionException(
                        "Cannot release order item {$orderItem->id} in state {$reservation->state->value}."
                    );
                }

                $balance->version++;
                $reservation->update([
                    'state' => InventoryReservationState::Released,
                    'released_at' => now(),
                    'release_reason' => $reason,
                ]);

                $ledger = $this->recordLedger(
                    balance: $balance,
                    type: $type,
                    deltas: $deltas,
                    idempotencyKey: $idempotencyKey,
                    orderId: $orderItem->order_id,
                    orderItemId: $orderItem->id,
                    correlationId: $correlationId,
                    reason: $reason,
                );

                $this->syncOrderInventoryStatus($orderItem->order_id);

                return ['reservation' => $reservation->fresh(), 'ledger' => $ledger];
            },
            orderItemId: $orderItem->id,
        );
    }

    /**
     * @return array{reservation: InventoryReservation, ledger: InventoryLedgerEntry}
     */
    public function expire(
        OrderItem $orderItem,
        string $idempotencyKey,
        ?string $correlationId = null,
    ): array {
        return $this->mutate(
            productIds: [$orderItem->product_id],
            idempotencyKey: $idempotencyKey,
            apply: function (Collection $balances) use ($orderItem, $correlationId, $idempotencyKey): array {
                $balance = $balances->get($orderItem->product_id);
                $quantity = (int) $orderItem->quantity;
                $reservation = InventoryReservation::query()->where('order_item_id', $orderItem->id)->first();

                if (! $reservation) {
                    throw new InvalidInventoryTransitionException(
                        "No reservation exists for order item {$orderItem->id}."
                    );
                }

                if ($reservation->state === InventoryReservationState::Expired) {
                    return $this->existingReservationResult($orderItem, $idempotencyKey);
                }

                if ($reservation->state !== InventoryReservationState::Reserved) {
                    throw new InvalidInventoryTransitionException(
                        "Cannot expire order item {$orderItem->id} in state {$reservation->state->value}."
                    );
                }

                $balance->reserved -= $quantity;
                $balance->version++;

                $reservation->update([
                    'state' => InventoryReservationState::Expired,
                    'expired_at' => now(),
                ]);

                $ledger = $this->recordLedger(
                    balance: $balance,
                    type: InventoryLedgerType::ReservationExpired,
                    deltas: ['reserved' => -$quantity],
                    idempotencyKey: $idempotencyKey,
                    orderId: $orderItem->order_id,
                    orderItemId: $orderItem->id,
                    correlationId: $correlationId,
                    reason: 'Payment reservation expired',
                );

                $this->syncOrderInventoryStatus($orderItem->order_id);

                return ['reservation' => $reservation->fresh(), 'ledger' => $ledger];
            },
            orderItemId: $orderItem->id,
        );
    }

    /**
     * @return array{reservation: InventoryReservation, ledger: InventoryLedgerEntry}
     */
    public function consume(
        OrderItem $orderItem,
        string $idempotencyKey,
        ?string $correlationId = null,
        ?string $reason = null,
    ): array {
        return $this->mutate(
            productIds: [$orderItem->product_id],
            idempotencyKey: $idempotencyKey,
            apply: function (Collection $balances) use ($orderItem, $correlationId, $reason, $idempotencyKey): array {
                $balance = $balances->get($orderItem->product_id);
                $quantity = (int) $orderItem->quantity;
                $reservation = InventoryReservation::query()->where('order_item_id', $orderItem->id)->first();

                if ($reservation && $reservation->state === InventoryReservationState::Consumed) {
                    return $this->existingReservationResult($orderItem, $idempotencyKey);
                }

                if (! $reservation || $reservation->state !== InventoryReservationState::Committed) {
                    throw new InvalidInventoryTransitionException(
                        "Cannot consume order item {$orderItem->id} without committed reservation."
                    );
                }

                if ($balance->committed < $quantity || $balance->on_hand < $quantity) {
                    throw new InsufficientInventoryException(
                        "Insufficient committed/on-hand stock to consume order item {$orderItem->id}."
                    );
                }

                $balance->on_hand -= $quantity;
                $balance->committed -= $quantity;
                $balance->version++;

                $reservation->update([
                    'state' => InventoryReservationState::Consumed,
                    'consumed_at' => now(),
                ]);

                $orderItem->forceFill([
                    'shipped_quantity' => max((int) $orderItem->shipped_quantity, $quantity),
                ])->save();

                $ledger = $this->recordLedger(
                    balance: $balance,
                    type: InventoryLedgerType::CourierHandoff,
                    deltas: ['on_hand' => -$quantity, 'committed' => -$quantity],
                    idempotencyKey: $idempotencyKey,
                    orderId: $orderItem->order_id,
                    orderItemId: $orderItem->id,
                    correlationId: $correlationId,
                    reason: $reason ?? 'Courier handoff',
                );

                $this->syncOrderInventoryStatus($orderItem->order_id);

                return ['reservation' => $reservation->fresh(), 'ledger' => $ledger];
            },
            orderItemId: $orderItem->id,
        );
    }

    /**
     * @param  'receive'|'decrease'|'set_count'|'set_available'  $operation
     * @return array{balance: InventoryBalance, ledger: InventoryLedgerEntry}
     */
    public function adjust(
        Product $product,
        string $operation,
        int $quantity,
        string $reason,
        string $idempotencyKey,
        ?int $expectedVersion = null,
        ?int $actorId = null,
        ?string $correlationId = null,
        ?array $metadata = null,
    ): array {
        return $this->mutate(
            productIds: [$product->id],
            idempotencyKey: $idempotencyKey,
            apply: function (Collection $balances) use (
                $product,
                $operation,
                $quantity,
                $reason,
                $expectedVersion,
                $actorId,
                $correlationId,
                $metadata,
                $idempotencyKey
            ): array {
                $balance = $balances->get($product->id);

                if ($expectedVersion !== null && (int) $balance->version !== $expectedVersion) {
                    throw new InventoryVersionConflictException(
                        "Inventory version mismatch for product {$product->id}."
                    );
                }

                $onHandDelta = match ($operation) {
                    'receive' => $quantity,
                    'decrease' => -$quantity,
                    'set_count' => $quantity - $balance->on_hand,
                    'set_available' => ($quantity + $balance->reserved + $balance->committed) - $balance->on_hand,
                    default => throw new InvalidInventoryTransitionException("Unknown adjustment operation [{$operation}]."),
                };

                if ($onHandDelta === 0) {
                    $existing = InventoryLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
                    if ($existing) {
                        return ['balance' => $balance, 'ledger' => $existing];
                    }
                }

                $nextOnHand = $balance->on_hand + $onHandDelta;

                if ($nextOnHand < 0) {
                    throw new InsufficientInventoryException(
                        "Adjustment would make on-hand negative for product {$product->id}."
                    );
                }

                if ($nextOnHand - $balance->reserved - $balance->committed < 0) {
                    throw new InsufficientInventoryException(
                        "Adjustment would make available stock negative for product {$product->id}."
                    );
                }

                $balance->on_hand = $nextOnHand;
                $balance->version++;

                $type = $onHandDelta >= 0
                    ? InventoryLedgerType::ManualReceipt
                    : InventoryLedgerType::ManualCorrection;

                $ledger = $this->recordLedger(
                    balance: $balance,
                    type: $type,
                    deltas: ['on_hand' => $onHandDelta],
                    idempotencyKey: $idempotencyKey,
                    actorId: $actorId,
                    correlationId: $correlationId,
                    reason: $reason,
                    metadata: $metadata,
                );

                return ['balance' => $balance, 'ledger' => $ledger];
            },
        );
    }

    /**
     * @return array{balance: InventoryBalance, ledger: InventoryLedgerEntry}
     */
    public function restockReturn(
        OrderItem $orderItem,
        int $quantity,
        string $idempotencyKey,
        ?int $actorId = null,
        ?string $correlationId = null,
        ?string $reason = null,
    ): array {
        if ($quantity < 1) {
            throw new InvalidInventoryTransitionException('Return quantity must be positive.');
        }

        return $this->mutate(
            productIds: [$orderItem->product_id],
            idempotencyKey: $idempotencyKey,
            apply: function (Collection $balances) use (
                $orderItem,
                $quantity,
                $actorId,
                $correlationId,
                $reason,
                $idempotencyKey
            ): array {
                $balance = $balances->get($orderItem->product_id);
                $restockable = max(0, (int) $orderItem->shipped_quantity - (int) $orderItem->restocked_quantity);

                if ($quantity > $restockable) {
                    throw new InvalidInventoryTransitionException(
                        "Return quantity exceeds shipped, unrestocked quantity for order item {$orderItem->id}."
                    );
                }

                $balance->on_hand += $quantity;
                $balance->version++;
                $orderItem->forceFill([
                    'returned_quantity' => max(
                        (int) $orderItem->returned_quantity,
                        (int) $orderItem->restocked_quantity + $quantity,
                    ),
                    'restocked_quantity' => (int) $orderItem->restocked_quantity + $quantity,
                ])->save();

                $ledger = $this->recordLedger(
                    balance: $balance,
                    type: InventoryLedgerType::ReturnRestocked,
                    deltas: ['on_hand' => $quantity],
                    idempotencyKey: $idempotencyKey,
                    orderId: $orderItem->order_id,
                    orderItemId: $orderItem->id,
                    actorId: $actorId,
                    correlationId: $correlationId,
                    reason: $reason ?? 'Returned inventory restocked',
                );

                return ['balance' => $balance, 'ledger' => $ledger];
            },
        );
    }

    /**
     * @return array{balance: InventoryBalance, ledger: InventoryLedgerEntry}
     */
    public function writeOffDamage(
        Product $product,
        int $quantity,
        string $reason,
        string $idempotencyKey,
        ?int $expectedVersion = null,
        ?int $actorId = null,
        ?string $correlationId = null,
        ?array $metadata = null,
    ): array {
        if ($quantity < 1) {
            throw new InvalidInventoryTransitionException('Damage quantity must be positive.');
        }

        return $this->mutate(
            productIds: [$product->id],
            idempotencyKey: $idempotencyKey,
            apply: function (Collection $balances) use (
                $product,
                $quantity,
                $reason,
                $expectedVersion,
                $actorId,
                $correlationId,
                $metadata,
                $idempotencyKey
            ): array {
                $balance = $balances->get($product->id);

                if ($expectedVersion !== null && (int) $balance->version !== $expectedVersion) {
                    throw new InventoryVersionConflictException(
                        "Inventory version mismatch for product {$product->id}."
                    );
                }

                if ($balance->available() < $quantity) {
                    throw new InsufficientInventoryException(
                        "Damage writeoff would consume allocated stock for product {$product->id}."
                    );
                }

                $balance->on_hand -= $quantity;
                $balance->version++;

                $ledger = $this->recordLedger(
                    balance: $balance,
                    type: InventoryLedgerType::DamagedWriteoff,
                    deltas: ['on_hand' => -$quantity],
                    idempotencyKey: $idempotencyKey,
                    actorId: $actorId,
                    correlationId: $correlationId,
                    reason: $reason,
                    metadata: $metadata,
                );

                return ['balance' => $balance, 'ledger' => $ledger];
            },
        );
    }

    /**
     * @return array{
     *   product_id: int,
     *   issues: list<array{code: string, message: string}>,
     *   repaired: bool
     * }
     */
    public function reconcile(Product $product, bool $repair = false): array
    {
        $issues = [];
        $balance = $this->ensureBalance($product, lock: true);

        $ledgerTotals = InventoryLedgerEntry::query()
            ->where('product_id', $product->id)
            ->selectRaw('COALESCE(SUM(on_hand_delta), 0) as on_hand_sum')
            ->selectRaw('COALESCE(SUM(reserved_delta), 0) as reserved_sum')
            ->selectRaw('COALESCE(SUM(committed_delta), 0) as committed_sum')
            ->first();

        if ((int) $ledgerTotals->on_hand_sum !== $balance->on_hand) {
            $issues[] = [
                'code' => 'balance_on_hand_mismatch',
                'message' => "On-hand {$balance->on_hand} differs from ledger sum {$ledgerTotals->on_hand_sum}.",
            ];
        }

        if ((int) $ledgerTotals->reserved_sum !== $balance->reserved) {
            $issues[] = [
                'code' => 'balance_reserved_mismatch',
                'message' => "Reserved {$balance->reserved} differs from ledger sum {$ledgerTotals->reserved_sum}.",
            ];
        }

        if ((int) $ledgerTotals->committed_sum !== $balance->committed) {
            $issues[] = [
                'code' => 'balance_committed_mismatch',
                'message' => "Committed {$balance->committed} differs from ledger sum {$ledgerTotals->committed_sum}.",
            ];
        }

        $reservationTotals = InventoryReservation::query()
            ->where('product_id', $product->id)
            ->selectRaw("SUM(CASE WHEN state = 'reserved' THEN quantity ELSE 0 END) as reserved_qty")
            ->selectRaw("SUM(CASE WHEN state = 'committed' THEN quantity ELSE 0 END) as committed_qty")
            ->first();

        if ((int) $reservationTotals->reserved_qty !== $balance->reserved) {
            $issues[] = [
                'code' => 'reservation_reserved_mismatch',
                'message' => 'Reserved balance does not match active reserved reservations.',
            ];
        }

        if ((int) $reservationTotals->committed_qty !== $balance->committed) {
            $issues[] = [
                'code' => 'reservation_committed_mismatch',
                'message' => 'Committed balance does not match active committed reservations.',
            ];
        }

        $available = $balance->available();
        if ((int) $product->stock !== $available) {
            $issues[] = [
                'code' => 'product_stock_projection_mismatch',
                'message' => "products.stock ({$product->stock}) differs from available ({$available}).",
            ];
        }

        if ($balance->reserved + $balance->committed > $balance->on_hand) {
            $issues[] = [
                'code' => 'negative_available_invariant',
                'message' => 'Reserved plus committed exceeds on-hand.',
            ];
        }

        $repaired = false;

        if ($repair && $issues !== []) {
            $idempotencyKey = 'reconcile:product:'.$product->id.':version:'.$balance->version;
            $this->mutate(
                productIds: [$product->id],
                idempotencyKey: $idempotencyKey,
                apply: function (Collection $balances) use (
                    $product,
                    $issues,
                    $ledgerTotals,
                    $reservationTotals,
                    $idempotencyKey,
                    &$repaired
                ): array {
                    $balance = $balances->get($product->id);
                    $targetReserved = (int) $reservationTotals->reserved_qty;
                    $targetCommitted = (int) $reservationTotals->committed_qty;
                    $targetOnHand = max(
                        0,
                        (int) $ledgerTotals->on_hand_sum,
                        $targetReserved + $targetCommitted,
                    );

                    $balance->forceFill([
                        'on_hand' => $targetOnHand,
                        'reserved' => $targetReserved,
                        'committed' => $targetCommitted,
                        'version' => $balance->version + 1,
                    ]);

                    $ledger = $this->recordLedger(
                        balance: $balance,
                        type: InventoryLedgerType::ReconciliationCorrection,
                        deltas: [
                            'on_hand' => $targetOnHand - (int) $ledgerTotals->on_hand_sum,
                            'reserved' => $targetReserved - (int) $ledgerTotals->reserved_sum,
                            'committed' => $targetCommitted - (int) $ledgerTotals->committed_sum,
                        ],
                        idempotencyKey: $idempotencyKey,
                        reason: 'Reconciliation repair',
                        metadata: ['issues' => $issues],
                    );
                    $repaired = true;

                    return ['balance' => $balance, 'ledger' => $ledger];
                },
            );
        }

        return [
            'product_id' => $product->id,
            'issues' => $issues,
            'repaired' => $repaired,
        ];
    }

    public function ensureBalance(Product $product, bool $lock = false): InventoryBalance
    {
        $query = InventoryBalance::query()->where('product_id', $product->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $balance = $query->first();

        if ($balance) {
            return $balance;
        }

        $balance = InventoryBalance::query()->create([
            'product_id' => $product->id,
            'on_hand' => (int) $product->stock,
            'reserved' => 0,
            'committed' => 0,
            'version' => 0,
            'low_stock_threshold' => $product->low_stock_threshold
                ?? config('inventory.default_low_stock_threshold'),
            'reorder_point' => $product->reorder_point
                ?? config('inventory.default_reorder_point'),
        ]);

        $this->recordLedger(
            balance: $balance,
            type: InventoryLedgerType::OpeningBalance,
            deltas: ['on_hand' => $balance->on_hand],
            idempotencyKey: 'opening:product:'.$product->id,
            reason: 'Inventory balance initialized from products.stock',
        );

        return $balance;
    }

    public function syncProductStock(Product $product, ?InventoryBalance $balance = null): void
    {
        $balance ??= $this->ensureBalance($product);
        $available = $balance->available();

        if ((int) $product->stock !== $available) {
            Product::query()->whereKey($product->id)->update(['stock' => $available]);
        }
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, InventoryBalance>
     */
    public function lockBalances(array $productIds): Collection
    {
        $ids = collect($productIds)->filter()->unique()->sort()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($ids as $productId) {
            $product = $products->get($productId);
            if ($product) {
                $this->ensureBalance($product);
            }
        }

        /** @var EloquentCollection<int, InventoryBalance> $balances */
        $balances = InventoryBalance::query()
            ->whereIn('product_id', $ids)
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('product_id');

        return $balances->sortKeys();
    }

    /**
     * @param  callable(Collection<int, InventoryBalance>): mixed  $apply
     */
    private function mutate(
        array $productIds,
        string $idempotencyKey,
        callable $apply,
        ?int $orderItemId = null,
    ): mixed {
        $existing = InventoryLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            $this->assertIdempotencyScope($existing, $productIds, $orderItemId);
            if ($orderItemId) {
                return $this->existingReservationResult(
                    OrderItem::query()->findOrFail($orderItemId),
                    $idempotencyKey,
                    $existing,
                );
            }

            return [
                'balance' => InventoryBalance::query()->where('product_id', $existing->product_id)->first(),
                'ledger' => $existing,
            ];
        }

        return DB::transaction(function () use ($productIds, $idempotencyKey, $apply, $orderItemId): mixed {
            $balances = $this->lockBalances($productIds);
            $existing = InventoryLedgerEntry::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertIdempotencyScope($existing, $productIds, $orderItemId);
                if ($orderItemId) {
                    return $this->existingReservationResult(
                        OrderItem::query()->findOrFail($orderItemId),
                        $idempotencyKey,
                        $existing,
                    );
                }

                return [
                    'balance' => $balances->get($existing->product_id),
                    'ledger' => $existing,
                ];
            }

            $result = $apply($balances);

            foreach ($balances as $balance) {
                $this->assertNonnegative($balance);
                $balance->save();
                $product = $balance->product()->first() ?? Product::query()->findOrFail($balance->product_id);
                $this->syncProductStock($product, $balance);
            }

            return $result;
        }, 3);
    }

    private function assertNonnegative(InventoryBalance $balance): void
    {
        if (
            $balance->on_hand < 0
            || $balance->reserved < 0
            || $balance->committed < 0
            || $balance->reserved + $balance->committed > $balance->on_hand
        ) {
            throw new InsufficientInventoryException(
                "Inventory mutation violates nonnegative invariants for product {$balance->product_id}."
            );
        }
    }

    /**
     * @param  list<int>  $productIds
     */
    private function assertIdempotencyScope(
        InventoryLedgerEntry $entry,
        array $productIds,
        ?int $orderItemId,
    ): void {
        if (
            ! in_array((int) $entry->product_id, array_map('intval', $productIds), true)
            || ($orderItemId !== null && (int) $entry->order_item_id !== $orderItemId)
        ) {
            throw new InvalidInventoryTransitionException(
                "Inventory idempotency key [{$entry->idempotency_key}] was already used for another mutation."
            );
        }
    }

    /**
     * @param  array{on_hand?: int, reserved?: int, committed?: int}  $deltas
     */
    private function recordLedger(
        InventoryBalance $balance,
        InventoryLedgerType $type,
        array $deltas,
        string $idempotencyKey,
        ?int $orderId = null,
        ?int $orderItemId = null,
        ?int $actorId = null,
        ?string $correlationId = null,
        ?string $reason = null,
        ?array $metadata = null,
    ): InventoryLedgerEntry {
        $entry = InventoryLedgerEntry::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $balance->product_id,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'actor_id' => $actorId,
            'type' => $type,
            'on_hand_delta' => $deltas['on_hand'] ?? 0,
            'reserved_delta' => $deltas['reserved'] ?? 0,
            'committed_delta' => $deltas['committed'] ?? 0,
            'on_hand_balance' => $balance->on_hand,
            'reserved_balance' => $balance->reserved,
            'committed_balance' => $balance->committed,
            'idempotency_key' => $idempotencyKey,
            'correlation_id' => $correlationId,
            'reason' => $reason,
            'metadata' => $metadata,
            'occurred_at' => Carbon::now(),
        ]);

        $this->outbox->record(
            'inventory.changed',
            'product',
            $balance->product_id,
            $idempotencyKey.':event',
            [
                'ledger_uuid' => $entry->uuid,
                'product_id' => $balance->product_id,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'type' => $type->value,
                'on_hand' => $balance->on_hand,
                'reserved' => $balance->reserved,
                'committed' => $balance->committed,
                'available' => $balance->available(),
            ],
        );

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertReservation(OrderItem $orderItem, array $attributes): InventoryReservation
    {
        return InventoryReservation::query()->updateOrCreate(
            ['order_item_id' => $orderItem->id],
            array_merge([
                'order_id' => $orderItem->order_id,
                'product_id' => $orderItem->product_id,
                'quantity' => (int) $orderItem->quantity,
            ], $attributes),
        );
    }

    private function syncOrderInventoryStatus(int $orderId): void
    {
        $states = InventoryReservation::query()
            ->where('order_id', $orderId)
            ->pluck('state')
            ->map(fn (InventoryReservationState $state) => $state->value);

        if ($states->isEmpty()) {
            return;
        }

        $status = match (true) {
            $states->every(fn (string $state) => $state === InventoryReservationState::Consumed->value) => OrderInventoryStatus::Consumed,
            $states->contains(InventoryReservationState::Committed->value) => OrderInventoryStatus::Committed,
            $states->contains(InventoryReservationState::Reserved->value) => OrderInventoryStatus::Reserved,
            $states->every(fn (string $state) => in_array($state, [
                InventoryReservationState::Released->value,
                InventoryReservationState::Expired->value,
            ], true)) => OrderInventoryStatus::Released,
            default => OrderInventoryStatus::Exception,
        };

        Order::query()->whereKey($orderId)->update([
            'inventory_status' => $status->value,
        ]);
    }

    /**
     * @return array{reservation: InventoryReservation, ledger: InventoryLedgerEntry}
     */
    private function existingReservationResult(
        OrderItem $orderItem,
        string $idempotencyKey,
        ?InventoryLedgerEntry $ledger = null,
    ): array {
        $ledger ??= InventoryLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();

        return [
            'reservation' => InventoryReservation::query()->where('order_item_id', $orderItem->id)->firstOrFail(),
            'ledger' => $ledger,
        ];
    }
}
