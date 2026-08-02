<?php

namespace App\Http\Resources;

use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'featured' => (bool) $this->featured,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'seo' => app(SeoService::class)->serializeForResource($this->resource),
        ];
    }
}
