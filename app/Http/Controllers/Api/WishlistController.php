<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\WishlistService;
use Illuminate\Http\Request;

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

    public function destroy(Request $request, Product $product)
    {
        $result = $this->wishlist->remove($request, $product);

        return response()->json([
            'count' => $result['count'],
            'product_ids' => $result['product_ids'],
        ]);
    }
}
