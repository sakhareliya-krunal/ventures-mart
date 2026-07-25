<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Services\ProductQueryService;

class CategoryController extends Controller
{
    public function __construct(private readonly ProductQueryService $products)
    {
    }

    public function index()
    {
        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();

        return CategoryResource::collection($categories);
    }

    public function show(string $slug)
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        return (new CategoryResource($category))->additional([
            'products' => ProductResource::collection(
                $this->products->query(['category' => $category->slug])
            ),
        ]);
    }
}
