<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Enums\OrderInventoryStatus;
use App\Exceptions\InsufficientInventoryException;
use App\Jobs\FulfillShiprocketOrder;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReplacementRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderReplacementService
{
    public const WINDOW_DAYS = 7;

    public const REASONS = ['damaged', 'defective', 'incorrect'];

    public const OPEN_STATUSES = ['requested', 'under_review', 'approved'];

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly FulfillmentAuditService $audit,
    ) {}

    public function canRequest(Order $order): bool
    {
        if ($order->status !== 'Delivered' || ($order->order_type ?: 'standard') === 'replacement') {
            return false;
        }

        $deliveredAt = $order->delivered_at ?: $order->updated_at;
        if (! $deliveredAt || $deliveredAt->lt(now()->subDays(self::WINDOW_DAYS))) {
            return false;
        }

        return ! $order->replacementRequests()
            ->whereIn('status', self::OPEN_STATUSES)
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forOrder(Order $order): array
    {
        return $order->replacementRequests()
            ->with('replacementOrder')
            ->latest('requested_at')
            ->get()
            ->map(fn (OrderReplacementRequest $request) => $this->toArray($request))
            ->values()
            ->all();
    }

    /**
     * @param  array{reason: string, notes?: string|null, order_item_id?: int|null, photos?: array<int, UploadedFile>}  $payload
     */
    public function request(Order $order, User $user, array $payload): OrderReplacementRequest
    {
        if (! $this->canRequest($order)) {
            throw ValidationException::withMessages([
                'order' => 'This order is not eligible for a replacement request.',
            ]);
        }

        $reason = (string) ($payload['reason'] ?? '');
        if (! in_array($reason, self::REASONS, true)) {
            throw ValidationException::withMessages([
                'reason' => 'Choose a valid replacement reason.',
            ]);
        }

        $itemId = $payload['order_item_id'] ?? null;
        if ($itemId) {
            $belongs = $order->items()->whereKey($itemId)->exists();
            if (! $belongs) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'Selected item does not belong to this order.',
                ]);
            }
        }

        $photoPaths = [];
        foreach ($payload['photos'] ?? [] as $photo) {
            $photoPaths[] = $photo->store('replacements/'.$order->id, 'public');
        }

        $request = OrderReplacementRequest::query()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'order_item_id' => $itemId,
            'status' => 'requested',
            'reason' => $reason,
            'notes' => isset($payload['notes']) ? trim((string) $payload['notes']) : null,
            'photo_paths' => $photoPaths ?: null,
            'requested_at' => now(),
        ]);

        $this->audit->record(
            $order,
            'replacement_requested',
            'customer',
            'replacement-request-'.$request->id,
            [
                'actor_user_id' => $user->id,
                'reason' => $reason,
                'metadata' => [
                    'replacement_request_id' => $request->id,
                    'order_item_id' => $itemId,
                ],
            ],
        );

        return $request->fresh(['replacementOrder']);
    }

    public function reject(OrderReplacementRequest $request, User $admin, string $reason): OrderReplacementRequest
    {
        if (! in_array($request->status, ['requested', 'under_review', 'approved'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This replacement request can no longer be rejected.',
            ]);
        }

        $request->forceFill([
            'status' => 'rejected',
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => trim($reason),
        ])->save();

        $this->audit->record(
            $request->order,
            'replacement_rejected',
            'admin',
            'replacement-reject-'.$request->id,
            [
                'actor_user_id' => $admin->id,
                'reason' => $reason,
                'metadata' => ['replacement_request_id' => $request->id],
            ],
        );

        return $request->fresh(['replacementOrder', 'order']);
    }

    public function approveAndFulfill(OrderReplacementRequest $request, User $admin): OrderReplacementRequest
    {
        if (! in_array($request->status, ['requested', 'under_review'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This replacement request cannot be approved.',
            ]);
        }

        $original = $request->order()->with('items')->firstOrFail();

        $replacement = DB::transaction(function () use ($request, $admin, $original) {
            $items = $original->items;
            if ($request->order_item_id) {
                $items = $items->where('id', $request->order_item_id)->values();
            }

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_item_id' => 'No items available for replacement.',
                ]);
            }

            $method = FulfillmentMethod::tryFrom((string) config('services.shiprocket.default_fulfillment_method', 'shiprocket'))
                ?? FulfillmentMethod::Manual;
            if (! config('services.shiprocket.enabled')) {
                $method = FulfillmentMethod::Manual;
            }

            $replacement = Order::query()->create([
                'number' => 'VM-R-'.Str::upper(Str::random(7)),
                'order_type' => 'replacement',
                'parent_order_id' => $original->id,
                'user_id' => $original->user_id,
                'full_name' => $original->full_name,
                'email' => $original->email,
                'phone' => $original->phone,
                'address' => $original->address,
                'city' => $original->city,
                'district' => $original->district,
                'state' => $original->state,
                'postal_code' => $original->postal_code,
                'seller_state' => $original->seller_state,
                'subtotal' => 0,
                'shipping' => 0,
                'cod_fee' => 0,
                'cgst' => 0,
                'sgst' => 0,
                'igst' => 0,
                'tax' => 0,
                'total' => 0,
                'status' => 'Processing',
                'inventory_status' => OrderInventoryStatus::Committed,
                'fulfillment_method' => $method,
                'payment_status' => 'paid',
                'payment_method' => $original->payment_method,
                'paid_at' => now(),
            ]);

            foreach ($items as $sourceItem) {
                /** @var OrderItem $sourceItem */
                $product = Product::query()->lockForUpdate()->find($sourceItem->product_id);
                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'stock' => ($sourceItem->product_name).' is no longer available for replacement.',
                    ]);
                }

                $item = $replacement->items()->create([
                    'product_id' => $sourceItem->product_id,
                    'product_name' => $sourceItem->product_name,
                    'product_sku' => $sourceItem->product_sku,
                    'hsn' => $sourceItem->hsn,
                    'product_slug' => $sourceItem->product_slug,
                    'product_image' => $sourceItem->product_image,
                    'unit_price' => 0,
                    'quantity' => (int) $sourceItem->quantity,
                    'weight_kg' => $sourceItem->weight_kg,
                    'length_cm' => $sourceItem->length_cm,
                    'breadth_cm' => $sourceItem->breadth_cm,
                    'height_cm' => $sourceItem->height_cm,
                    'line_total' => 0,
                ]);

                try {
                    $this->inventory->commit(
                        $item,
                        "order:{$replacement->id}:item:{$item->id}:replacement-commit",
                        fromReserved: false,
                        correlationId: 'order:'.$replacement->id,
                        reason: 'Replacement order inventory commit',
                    );
                } catch (InsufficientInventoryException $exception) {
                    throw ValidationException::withMessages([
                        'stock' => ($sourceItem->product_name).' does not have enough stock for replacement.',
                    ]);
                }
            }

            $request->forceFill([
                'status' => 'fulfilled',
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'replacement_order_id' => $replacement->id,
            ])->save();

            return $replacement->fresh(['items']);
        });

        if ($replacement->fulfillment_method === FulfillmentMethod::Shiprocket) {
            FulfillShiprocketOrder::dispatch($replacement->id);
        }
        SendOrderConfirmationEmail::dispatch($replacement->id);

        $this->audit->record(
            $original,
            'replacement_fulfilled',
            'admin',
            'replacement-fulfill-'.$request->id,
            [
                'actor_user_id' => $admin->id,
                'metadata' => [
                    'replacement_request_id' => $request->id,
                    'replacement_order_id' => $replacement->id,
                    'replacement_order_number' => $replacement->number,
                ],
            ],
        );

        return $request->fresh(['replacementOrder', 'order', 'orderItem']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(OrderReplacementRequest $request): array
    {
        return [
            'id' => $request->id,
            'order_id' => $request->order_id,
            'order_item_id' => $request->order_item_id,
            'status' => $request->status,
            'reason' => $request->reason,
            'notes' => $request->notes,
            'photo_urls' => collect($request->photo_paths ?? [])
                ->map(fn ($path) => Storage::disk('public')->url($path))
                ->values()
                ->all(),
            'rejection_reason' => $request->rejection_reason,
            'requested_at' => $request->requested_at?->toIso8601String(),
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'replacement_order' => $request->replacementOrder ? [
                'id' => $request->replacementOrder->id,
                'number' => $request->replacementOrder->number,
                'status' => $request->replacementOrder->status,
            ] : null,
        ];
    }
}
