<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly CartService $cart,
        private readonly ProductQueryService $products,
    ) {
    }

    public function create(Request $request, array $address): Order
    {
        $cartItems = $this->cart->items($request);

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        return DB::transaction(function () use ($request, $address, $cartItems) {
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

            $totals = $this->products->calculateTotals(collect($lines)->map(fn ($line) => [
                'price' => $line['price'],
                'quantity' => $line['quantity'],
            ]));

            $order = Order::query()->create([
                'number' => 'VM-'.Str::upper(Str::random(8)),
                'user_id' => $request->user()?->id,
                'full_name' => $address['full_name'],
                'email' => $address['email'],
                'phone' => $address['phone'],
                'address' => $address['address'],
                'city' => $address['city'],
                'state' => $address['state'],
                'postal_code' => $address['postal_code'],
                'subtotal' => $totals['subtotal'],
                'shipping' => $totals['shipping'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'status' => 'Processing',
            ]);

            foreach ($lines as $line) {
                $product = $line['product'];
                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'product_slug' => $product->slug,
                    'product_image' => $product->image,
                    'unit_price' => $product->price,
                    'quantity' => $line['quantity'],
                    'line_total' => round($product->price * $line['quantity'], 2),
                ]);

                $product->decrement('stock', $line['quantity']);
            }

            $this->cart->clear($request);

            if (! $request->user()) {
                $ids = collect($request->session()->get('guest_order_ids', []));
                $ids->push($order->id);
                $request->session()->put('guest_order_ids', $ids->unique()->values()->all());
            }

            return $order->load('items');
        });
    }
}
