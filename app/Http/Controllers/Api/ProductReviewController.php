<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductReviewResource;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function index(string $slug)
    {
        $product = Product::query()->where('slug', $slug)->firstOrFail();

        $reviews = ProductReview::query()
            ->where('product_id', $product->id)
            ->latest()
            ->get();

        return ProductReviewResource::collection($reviews);
    }

    public function store(Request $request, string $slug)
    {
        $product = Product::query()->where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'author_name' => ['required', 'string', 'min:2', 'max:80'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $review = ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()?->id,
            'author_name' => trim($data['author_name']),
            'rating' => (int) $data['rating'],
            'body' => trim($data['body']),
        ]);

        $product->refreshReviewAggregates();

        return (new ProductReviewResource($review))
            ->additional([
                'product' => [
                    'rating' => (float) $product->rating,
                    'reviews' => (int) $product->reviews,
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }
}
