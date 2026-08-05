<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\SeoCache;
use App\Services\SeoRedirectService;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function __construct(
        private readonly SeoService $seo,
        private readonly SeoRedirectService $redirects,
    ) {
    }

    public function index(Request $request)
    {
        $query = Post::query()->with('seoMetadata')->latest('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return PostResource::collection(
            $query->paginate(min((int) $request->integer('per_page', 20), 100))
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $seoPayload = $validated['seo'] ?? null;
        $faqsPayload = $validated['faqs'] ?? null;
        unset($validated['seo'], $validated['faqs']);
        $post = Post::query()->create($validated);
        $this->seo->updateFor($post, $seoPayload, $faqsPayload);
        SeoCache::forgetSitemap();
        $post->load(['seoMetadata', 'seoFaqs']);

        return (new PostResource($post))->response()->setStatusCode(201);
    }

    public function show(Post $post)
    {
        $post->load(['seoMetadata', 'seoFaqs']);

        return new PostResource($post);
    }

    public function update(Request $request, Post $post)
    {
        $oldSlug = $post->slug;
        $validated = $this->validated($request, $post);
        $seoPayload = $validated['seo'] ?? null;
        $faqsPayload = $validated['faqs'] ?? null;
        unset($validated['seo'], $validated['faqs']);
        $post->fill($validated)->save();
        $this->redirects->redirectSlugChange('/blog/'.$oldSlug, '/blog/'.$post->slug);
        $this->seo->updateFor($post, $seoPayload, $faqsPayload);
        SeoCache::forgetSitemap();
        $post->load(['seoMetadata', 'seoFaqs']);

        return new PostResource($post);
    }

    public function destroy(Post $post)
    {
        $post->delete();
        SeoCache::forgetSitemap();

        return response()->json(['message' => 'Post deleted.']);
    }

    private function validated(Request $request, ?Post $post = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'cover_image' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
        ] + $this->seo->seoRules());

        $validated['slug'] = $this->uniqueSlug(
            $validated['slug'] ?? null,
            $validated['title'],
            $post?->id,
        );

        return $validated;
    }

    private function uniqueSlug(?string $slug, string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug((string) $slug) ?: Str::slug($title) ?: 'post-'.Str::lower(Str::random(8));
        $candidate = $base;
        $suffix = 2;

        while (
            Post::query()
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
