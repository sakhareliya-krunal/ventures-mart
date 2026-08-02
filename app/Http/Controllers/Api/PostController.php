<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::query()
            ->published()
            ->latest('published_at')
            ->get();

        return PostResource::collection($posts);
    }

    public function show(string $slug)
    {
        $post = Post::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $related = Post::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->latest('published_at')
            ->limit(3)
            ->get(['id', 'slug', 'title', 'excerpt', 'cover_image', 'published_at']);

        return (new PostResource($post))->additional([
            'related' => $related->map(fn (Post $item) => [
                'id' => $item->id,
                'slug' => $item->slug,
                'title' => $item->title,
                'excerpt' => $item->excerpt,
                'cover_image' => $item->cover_image,
                'published_at' => $item->published_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
