<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->when(
                $request->route('slug') || $request->route('post') || $request->is('api/admin/*'),
                $this->body,
            ),
            'cover_image' => $this->cover_image,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
