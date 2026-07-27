<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\WishlistService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WishlistController extends Controller
{
    public function __construct(private readonly WishlistService $wishlist)
    {
    }

    public function index(Request $request)
    {
        $items = $this->wishlist->items($request);

        return response()->json([
            'count' => $items->count(),
            'product_ids' => $items->pluck('product_id')->all(),
            'products' => ProductResource::collection($items->pluck('product')->filter()),
        ]);
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $result = $this->wishlist->toggle($request, $product);

        return response()->json([
            'wished' => $result['wished'],
            'count' => $result['count'],
            'product_ids' => $result['product_ids'],
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $product = Product::query()->active()->find($data['product_id']);

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => ['This product is unavailable.'],
            ]);
        }

        if (! empty($data['variant_id'])) {
            $variant = Product::query()->active()->find($data['variant_id']);

            if (! $variant) {
                throw ValidationException::withMessages([
                    'variant_id' => ['This variant is unavailable.'],
                ]);
            }

            $sameProduct = (int) $variant->id === (int) $product->id;
            $sameGroup = $product->variant_group_id
                && $variant->variant_group_id
                && (string) $product->variant_group_id === (string) $variant->variant_group_id;

            if (! $sameProduct && ! $sameGroup) {
                throw ValidationException::withMessages([
                    'variant_id' => ['The selected variant does not belong to this product.'],
                ]);
            }

            // Wishlist the concrete selected variant row when provided.
            $product = $variant;
        }

        $result = $this->wishlist->add($request, $product);

        return response()->json([
            'wished' => $result['wished'],
            'added' => $result['added'],
            'count' => $result['count'],
            'product_ids' => $result['product_ids'],
        ]);
    }

    public function destroy(Request $request, Product $product)
    {
        $result = $this->wishlist->remove($request, $product);

        return response()->json([
            'count' => $result['count'],
            'product_ids' => $result['product_ids'],
        ]);
    }
}
