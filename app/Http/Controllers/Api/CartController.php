<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart)
    {
    }

    public function show(Request $request)
    {
        return $this->format($this->cart->payload($request));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);

        return $this->format($this->cart->add($request, $product, $data['quantity'] ?? 1));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        return $this->format($this->cart->update($request, $product, $data['quantity']));
    }

    public function destroy(Request $request, Product $product)
    {
        return $this->format($this->cart->remove($request, $product));
    }

    public function clear(Request $request)
    {
        $this->cart->clear($request);

        return $this->format($this->cart->payload($request));
    }

    private function format(array $payload)
    {
        return response()->json([
            'items' => CartItemResource::collection($payload['items']),
            'item_count' => $payload['item_count'],
            'totals' => $payload['totals'],
        ]);
    }
}
