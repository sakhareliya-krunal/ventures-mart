<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductResource;
use App\Services\ProductQueryService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductQueryService $products)
    {
    }

    public function index(Request $request)
    {
        $items = $this->products->query($request->only(['q', 'category', 'min_price', 'max_price', 'sort']));

        return ProductCardResource::collection($items);
    }

    public function priceBounds()
    {
        return response()->json($this->products->priceBounds());
    }

    public function featured()
    {
        return ProductCardResource::collection($this->products->featured());
    }

    public function sale()
    {
        return ProductCardResource::collection($this->products->sale());
    }

    public function show(string $slug)
    {
        $product = $this->products->findBySlug($slug);

        abort_if(! $product, 404);

        return (new ProductResource($product))->additional([
            'related' => ProductCardResource::collection($this->products->related($product)),
        ]);
    }
}
