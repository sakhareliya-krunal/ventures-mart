<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\ImageVariantService;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin Product */
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
            'hsn' => $this->hsn,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->slug),
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'price' => (float) $this->price,
            'compare_at_price' => $this->compare_at_price !== null ? (float) $this->compare_at_price : null,
            'rating' => (float) $this->rating,
            'reviews' => (int) $this->reviews,
            'image' => $this->image,
            'image_srcset' => app(ImageVariantService::class)->srcsetForPublicUrl($this->image),
            'hover_image' => $this->hover_image,
            'hover_image_srcset' => app(ImageVariantService::class)->srcsetForPublicUrl($this->hover_image),
            'badge' => $this->badge,
            'tags' => $this->tags ?? [],
            'description' => $this->description,
            'details' => $this->details ?? [],
            'specifications' => $this->specifications ?? [],
            'stock' => (int) $this->stock,
            'is_low_stock' => $this->isLowStock(),
            'weight_kg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'length_cm' => $this->length_cm !== null ? (float) $this->length_cm : null,
            'breadth_cm' => $this->breadth_cm !== null ? (float) $this->breadth_cm : null,
            'height_cm' => $this->height_cm !== null ? (float) $this->height_cm : null,
            'is_active' => (bool) $this->is_active,
            'variant_group_id' => $this->variant_group_id,
            'color_name' => $this->color_name,
            'color_hex' => $this->color_hex,
            'gallery' => $this->localGallery(),
            'gallery_srcsets' => collect($this->localGallery())
                ->mapWithKeys(fn (string $image) => [$image => app(ImageVariantService::class)->srcsetForPublicUrl($image, ImageVariantService::DETAIL_WIDTHS)])
                ->filter()
                ->all(),
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
                'image_srcset' => app(ImageVariantService::class)->srcsetForPublicUrl($variant->image),
                'hover_image' => $variant->hover_image,
                'hover_image_srcset' => app(ImageVariantService::class)->srcsetForPublicUrl($variant->hover_image),
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
