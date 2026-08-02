<?php

namespace App\Http\Resources;

use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'sku' => $this->sku,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->slug),
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'rating' => (float) $this->rating,
            'reviews' => (int) $this->reviews,
            'image' => $this->image,
            'hover_image' => $this->hover_image,
            'badge' => $this->badge,
            'tags' => $this->tags ?? [],
            'description' => $this->description,
            'details' => $this->details ?? [],
            'specifications' => $this->specifications ?? [],
            'stock' => (int) $this->stock,
            'is_active' => (bool) $this->is_active,
            'variant_group_id' => $this->variant_group_id,
            'color_name' => $this->color_name,
            'color_hex' => $this->color_hex,
            'gallery' => $this->localGallery(),
            'variants' => $this->variantPayload(),
            'seo' => app(SeoService::class)->serializeForResource($this->resource),
        ];
    }

    private function variantPayload(): array
    {
        /** @var Collection $siblings */
        $siblings = $this->relationLoaded('colorVariants')
            ? $this->getRelation('colorVariants')
            : $this->siblingVariants();

        return $siblings
            ->filter(fn ($variant) => $variant->is_active || $variant->id === $this->id)
            ->map(fn ($variant) => [
            'id' => $variant->id,
            'slug' => $variant->slug,
            'name' => $variant->name,
            'image' => $variant->image,
            'hover_image' => $variant->hover_image,
            'price' => (float) $variant->price,
            'compare_at_price' => $variant->compare_at_price !== null ? (float) $variant->compare_at_price : null,
            'rating' => (float) $variant->rating,
            'reviews' => (int) $variant->reviews,
            'stock' => (int) $variant->stock,
            'badge' => $variant->badge,
            'color_name' => $variant->color_name,
            'color_hex' => $variant->color_hex,
        ])->values()->all();
    }
}
