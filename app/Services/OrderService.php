<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
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
    ) {
    }

    /**
     * Create an order. Razorpay orders stay unpaid until verify; COD finalizes stock/cart immediately.
     *
     * @param  array{
     *     full_name: string,
     *     email: string,
     *     phone: string,
     *     address: string,
     *     city: string,
     *     state: string,
     *     postal_code: string,
     *     payment_method: string
     * }  $payload
     */
    public function create(Request $request, array $payload): Order
    {
        $paymentMethod = $payload['payment_method'];
        $cartItems = $this->cart->items($request);

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $order = DB::transaction(function () use ($request, $payload, $cartItems, $paymentMethod) {
            $lines = [];

            foreach ($cartItems as $item) {
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
            $codFee = $isCod ? (float) config('checkout.cod_fee', 100) : 0.0;

            $order = Order::query()->create([
                'number' => 'VM-'.Str::upper(Str::random(8)),
                'user_id' => $request->user()?->id,
                'full_name' => $payload['full_name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'address' => $payload['address'],
                'city' => $payload['city'],
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
                'payment_status' => 'pending',
                'payment_method' => $paymentMethod,
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
                    'line_total' => round($product->price * $line['quantity'], 2),
                ]);
            }

            if ($isCod) {
                $order->load('items');
                $this->decrementStockForOrder($order);
            }

            return $order->load('items');
        });

        if ($paymentMethod === 'cod') {
            $this->cart->clear($request);

            return $order->fresh('items');
        }

        try {
            $razorpayOrder = $this->razorpay->createOrder($order);
        } catch (Throwable $e) {
            $order->forceFill(['payment_status' => 'failed'])->save();

            throw $e;
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

        return DB::transaction(function () use ($request, $order, $payload) {
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
    }

    /**
     * Mark an order paid from a verified Razorpay webhook (no browser session / cart clear).
     */
    public function markPaidFromWebhook(Order $order, string $paymentId): Order
    {
        if ($order->payment_status === 'paid') {
            return $order->load('items');
        }

        return DB::transaction(function () use ($order, $paymentId) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $locked->load('items');

            if ($locked->payment_status === 'paid') {
                return $locked;
            }

            $this->finalizePaidOrder($locked, $paymentId, null);

            return $locked->load('items');
        });
    }

    private function finalizePaidOrder(Order $order, string $paymentId, ?string $signature): void
    {
        $this->decrementStockForOrder($order);

        $order->forceFill([
            'payment_status' => 'paid',
            'payment_method' => 'razorpay',
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
            'paid_at' => now(),
            'status' => 'Processing',
        ])->save();
    }

    private function decrementStockForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            /** @var Product|null $product */
            $product = Product::query()->lockForUpdate()->find($item->product_id);

            if (! $product || ! $product->is_active || $product->stock < $item->quantity) {
                throw ValidationException::withMessages([
                    'stock' => ($product?->name ?? 'A product').' is no longer available in the required quantity.',
                ]);
            }

            $product->decrement('stock', $item->quantity);
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
