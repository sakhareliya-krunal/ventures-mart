<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Enums\InventoryReservationState;
use App\Enums\OrderInventoryStatus;
use App\Exceptions\InsufficientInventoryException;
use App\Exceptions\PaymentInitializationException;
use App\Jobs\FulfillShiprocketOrder;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use App\Support\GstState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly ProductQueryService $products,
        private readonly RazorpayService $razorpay,
        private readonly InventoryService $inventory,
        private readonly FulfillmentAuditService $fulfillmentAudit,
    ) {}

    /**
     * Create an order. Razorpay orders stay unpaid until verify; COD finalizes stock/cart immediately.
     *
     * @param  array{
     *     full_name: string,
     *     email: string,
     *     phone: string,
     *     address: string,
     *     city: string,
     *     district?: string|null,
     *     state: string,
     *     postal_code: string,
     *     payment_method: string
     * }  $payload
     */
    public function create(Request $request, array $payload): Order
    {
        $paymentMethod = $payload['payment_method'];
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        $idempotencyKey = $idempotencyKey !== '' ? mb_substr($idempotencyKey, 0, 100) : null;

        if ($idempotencyKey) {
            $existing = Order::query()
                ->where('checkout_idempotency_key', $idempotencyKey)
                ->where('user_id', $request->user()?->id)
                ->first();

            if ($existing) {
                $existing->load('items');
                $this->dispatchOrderConfirmationEmail($existing);

                return $existing;
            }
        }

        $cartItems = $this->cart->items($request);

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $order = DB::transaction(function () use ($request, $payload, $cartItems, $paymentMethod, $idempotencyKey) {
            $lines = [];

            foreach ($cartItems->sortBy('product_id') as $item) {
                /** @var Product $product */
                $product = Product::query()->lockForUpdate()->find($item->product_id);

                if (! $product || ! $product->is_active || $product->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => ! $product || ! $product->is_active
                            ? ($product?->name ?? 'A product').' is no longer available.'
                            : ($product->name).' does not have enough stock.',
                    ]);
                }

                $lines[] = [
                    'product' => $product,
                    'quantity' => $item->quantity,
                    'price' => $product->price,
                ];
            }

            $normalizedState = GstState::normalize($payload['state']) ?? $payload['state'];

            $totals = $this->products->calculateTotals(collect($lines)->map(fn ($line) => [
                'price' => $line['price'],
                'quantity' => $line['quantity'],
            ]), $normalizedState);

            $isCod = $paymentMethod === 'cod';
            $codFee = $isCod ? (float) config('checkout.cod_fee', 99) : 0.0;

            $order = Order::query()->create([
                'number' => 'VM-'.Str::upper(Str::random(8)),
                'checkout_idempotency_key' => $idempotencyKey,
                'user_id' => $request->user()?->id,
                'full_name' => $payload['full_name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'address' => $payload['address'],
                'city' => $payload['city'],
                'district' => $payload['district'] ?? null,
                'state' => $normalizedState,
                'postal_code' => $payload['postal_code'],
                'seller_state' => GstState::sellerState(),
                'subtotal' => $totals['subtotal'],
                'shipping' => $totals['shipping'],
                'cod_fee' => $codFee,
                'cgst' => $totals['cgst'],
                'sgst' => $totals['sgst'],
                'igst' => $totals['igst'],
                'tax' => $totals['tax'],
                'total' => round($totals['total'] + $codFee, 2),
                'status' => $isCod ? 'Processing' : 'AwaitingPayment',
                'fulfillment_method' => $this->configuredFulfillmentMethod(),
                'payment_status' => 'pending',
                'payment_method' => $paymentMethod,
                'payment_expires_at' => $isCod
                    ? null
                    : now()->addMinutes((int) config('inventory.payment_reservation_ttl_minutes', 15)),
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'hsn' => $product->hsn ?: config('invoice.default_hsn'),
                    'product_slug' => $product->slug,
                    'product_image' => $product->image,
                    'unit_price' => $product->price,
                    'quantity' => $line['quantity'],
                    'weight_kg' => $product->weight_kg ?: config('services.shiprocket.fallback_weight_kg', 0.5),
                    'length_cm' => $product->length_cm ?: config('services.shiprocket.fallback_length_cm', 20),
                    'breadth_cm' => $product->breadth_cm ?: config('services.shiprocket.fallback_breadth_cm', 15),
                    'height_cm' => $product->height_cm ?: config('services.shiprocket.fallback_height_cm', 10),
                    'line_total' => round($product->price * $line['quantity'], 2),
                ]);
            }

            $this->fulfillmentAudit->record(
                $order,
                'method_assigned',
                'checkout',
                "order:{$order->id}:fulfillment-method-assigned",
                [
                    'new_method' => $order->fulfillment_method,
                    'reason' => 'Assigned by server fulfillment configuration',
                ],
            );

            if ($isCod) {
                $order->load('items');
                $this->commitInventoryForOrder($order);
            } else {
                $order->load('items');
                $this->reserveInventoryForOrder($order);
            }

            return $order->load('items');
        });

        if ($paymentMethod === 'cod') {
            $this->cart->clear($request);
            $this->dispatchShiprocketFulfillment($order);
            $this->dispatchOrderConfirmationEmail($order);

            return $order->fresh('items');
        }

        try {
            $razorpayOrder = $this->razorpay->createOrder($order);
        } catch (Throwable $e) {
            $this->releaseInventoryForOrder($order, 'Razorpay initialization failed', 'payment-init-failed');
            $order->forceFill([
                'payment_status' => 'failed',
                'inventory_status' => OrderInventoryStatus::Released,
            ])->save();

            throw new PaymentInitializationException(
                'Unable to start payment. Please try again.',
                previous: $e,
            );
        }

        $order->forceFill([
            'razorpay_order_id' => $razorpayOrder['id'],
        ])->save();

        return $order->fresh('items');
    }

    /**
     * @param  array{razorpay_order_id: string, razorpay_payment_id: string, razorpay_signature: string}  $payload
     */
    public function verifyPayment(Request $request, Order $order, array $payload): Order
    {
        if ($order->payment_status === 'paid') {
            $this->dispatchShiprocketFulfillment($order);
            $this->dispatchOrderConfirmationEmail($order);

            return $order->load('items');
        }

        if ($order->payment_method !== 'razorpay') {
            throw ValidationException::withMessages([
                'payment' => 'This order is not payable online.',
            ]);
        }

        if ($order->razorpay_order_id !== $payload['razorpay_order_id']) {
            throw ValidationException::withMessages([
                'razorpay_order_id' => 'Payment does not match this order.',
            ]);
        }

        $valid = $this->razorpay->verifySignature(
            $payload['razorpay_order_id'],
            $payload['razorpay_payment_id'],
            $payload['razorpay_signature'],
        );

        if (! $valid) {
            $this->releaseInventoryForOrder($order, 'Razorpay signature verification failed', 'signature-failed');
            $order->forceFill(['payment_status' => 'failed'])->save();
            app(ApplicationErrorRecorder::class)->recordPaymentFailure(
                'Razorpay signature verification failed',
                [
                    'order_id' => $order->id,
                    'order_number' => $order->number,
                    'razorpay_order_id' => $payload['razorpay_order_id'],
                    'razorpay_payment_id' => $payload['razorpay_payment_id'],
                ],
            );

            throw ValidationException::withMessages([
                'payment' => 'Payment verification failed. If you were charged, contact support with your order number.',
            ]);
        }

        $paidOrder = DB::transaction(function () use ($request, $order, $payload) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->load('items');

            if ($locked->payment_status === 'paid') {
                return $locked;
            }

            $this->finalizePaidOrder($locked, $payload['razorpay_payment_id'], $payload['razorpay_signature']);
            $this->cart->clear($request);

            return $locked->load('items');
        });

        $this->dispatchShiprocketFulfillment($paidOrder);
        $this->dispatchOrderConfirmationEmail($paidOrder);

        return $paidOrder;
    }

    /**
     * Mark an order paid from a verified Razorpay webhook (no browser session / cart clear).
     */
    public function markPaidFromWebhook(Order $order, string $paymentId): Order
    {
        if ($order->payment_status === 'paid') {
            $this->dispatchShiprocketFulfillment($order);
            $this->dispatchOrderConfirmationEmail($order);

            return $order->load('items');
        }

        $paidOrder = DB::transaction(function () use ($order, $paymentId) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->load('items');

            if ($locked->payment_status === 'paid') {
                return $locked;
            }

            $this->finalizePaidOrder($locked, $paymentId, null);

            return $locked->load('items');
        });

        $this->dispatchShiprocketFulfillment($paidOrder);
        $this->dispatchOrderConfirmationEmail($paidOrder);

        return $paidOrder;
    }

    private function finalizePaidOrder(Order $order, string $paymentId, ?string $signature): void
    {
        try {
            $this->commitInventoryForOrder($order);
        } catch (InsufficientInventoryException $exception) {
            $order->forceFill([
                'payment_status' => 'paid',
                'payment_method' => 'razorpay',
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
                'paid_at' => now(),
                'status' => 'InventoryHold',
                'inventory_status' => OrderInventoryStatus::Exception,
            ])->save();

            app(ApplicationErrorRecorder::class)->recordThrowable($exception, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'phase' => 'late_payment_inventory_reacquisition',
            ], 'inventory');

            return;
        }

        $order->forceFill([
            'payment_status' => 'paid',
            'payment_method' => 'razorpay',
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
            'paid_at' => now(),
            'status' => 'Processing',
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ])->save();
    }

    private function commitInventoryForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            /** @var Product|null $product */
            $product = Product::query()->lockForUpdate()->find($item->product_id);
            $reservation = $item->inventoryReservation()->first();
            $hasPaymentReservation = $order->payment_method === 'razorpay' && $reservation !== null;

            if (
                ! $product
                || ! $product->is_active
                || (! $hasPaymentReservation && $product->stock < $item->quantity)
            ) {
                throw ValidationException::withMessages([
                    'stock' => ($product?->name ?? 'A product').' is no longer available in the required quantity.',
                ]);
            }

            $this->inventory->commit(
                $item,
                "order:{$order->id}:item:{$item->id}:commit",
                fromReserved: $order->payment_method === 'razorpay' && $reservation !== null,
                correlationId: 'order:'.$order->id,
                reason: $order->payment_method === 'cod'
                    ? 'COD order commitment'
                    : 'Razorpay payment confirmed',
            );
        }
    }

    private function reserveInventoryForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            $this->inventory->reserve(
                $item,
                $order->payment_expires_at,
                "order:{$order->id}:item:{$item->id}:reserve",
                correlationId: 'order:'.$order->id,
                reason: 'Razorpay checkout reservation',
            );
        }
    }

    private function releaseInventoryForOrder(Order $order, string $reason, string $suffix): void
    {
        $order->loadMissing('items.inventoryReservation');

        foreach ($order->items as $item) {
            $reservation = $item->inventoryReservation;
            if (! $reservation || ! in_array($reservation->state, [
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

    private function dispatchShiprocketFulfillment(Order $order): void
    {
        if (
            ! (bool) config('services.shiprocket.enabled')
            || $order->fulfillment_method !== FulfillmentMethod::Shiprocket
            || $order->inventory_status === OrderInventoryStatus::Exception
        ) {
            return;
        }

        try {
            FulfillShiprocketOrder::dispatch($order->id);
        } catch (Throwable $e) {
            app(ApplicationErrorRecorder::class)->recordThrowable($e, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'phase' => 'shiprocket_enqueue',
            ], 'fulfillment');
        }
    }

    private function configuredFulfillmentMethod(): FulfillmentMethod
    {
        $configured = FulfillmentMethod::tryFrom(
            strtolower(trim((string) config('services.shiprocket.default_fulfillment_method')))
        ) ?? FulfillmentMethod::Manual;

        if ($configured === FulfillmentMethod::Shiprocket && ! config('services.shiprocket.enabled')) {
            return FulfillmentMethod::Manual;
        }

        return $configured;
    }

    private function dispatchOrderConfirmationEmail(Order $order): void
    {
        if (
            $order->order_confirmation_emailed_at
            || in_array($order->status, ['Cancelled', 'InventoryHold'], true)
            || ($order->payment_method === 'razorpay' && $order->payment_status !== 'paid')
        ) {
            return;
        }

        try {
            SendOrderConfirmationEmail::dispatch($order->id);
        } catch (Throwable $e) {
            app(ApplicationErrorRecorder::class)->recordThrowable($e, [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'phase' => 'order_confirmation_email_enqueue',
            ], 'email');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function razorpayCheckoutPayload(Order $order): array
    {
        if (! $order->razorpay_order_id) {
            throw new RuntimeException('Razorpay order is missing for this local order.');
        }

        return $this->razorpay->checkoutPayload($order);
    }
}
