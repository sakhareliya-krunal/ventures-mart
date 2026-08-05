<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\SeoAutoGenerator;
use App\Services\SeoCache;
use App\Services\SeoRedirectService;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(
        private readonly SeoService $seo,
        private readonly SeoAutoGenerator $autoSeo,
        private readonly SeoRedirectService $redirects,
    ) {
    }

    public function index()
    {
        return CategoryResource::collection(
            Category::query()->with(['seoMetadata'])->orderBy('sort_order')->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $seoPayload = $validated['seo'] ?? null;
        $faqsPayload = $validated['faqs'] ?? null;
        unset($validated['seo'], $validated['faqs']);
        $category = Category::query()->create($validated);

        $merged = $this->autoSeo->syncSeoSlug(
            $this->autoSeo->merge(
                $this->autoSeo->forCategory($category),
                [],
                $seoPayload,
            ),
            $category->slug,
        );
        $this->seo->updateFor($category, $merged, $faqsPayload);
        SeoCache::forgetSitemap();
        $category->load(['seoMetadata', 'seoFaqs']);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category)
    {
        $category->load(['seoMetadata', 'seoFaqs']);

        return new CategoryResource($category);
    }

    public function update(Request $request, Category $category)
    {
        $oldSlug = $category->slug;
        $validated = $this->validated($request, $category);
        $seoPayload = $validated['seo'] ?? null;
        $faqsPayload = $validated['faqs'] ?? null;
        unset($validated['seo'], $validated['faqs']);
        $category->fill($validated)->save();
        $this->redirects->redirectSlugChange('/category/'.$oldSlug, '/category/'.$category->slug);

        $existing = $this->seo->metadataPayload($category->seoMetadata);
        $merged = $this->autoSeo->syncSeoSlug(
            $this->autoSeo->merge(
                $this->autoSeo->forCategory($category),
                $existing,
                $seoPayload,
            ),
            $category->slug,
        );
        $this->seo->updateFor($category, $merged, $faqsPayload);
        SeoCache::forgetSitemap();
        $category->load(['seoMetadata', 'seoFaqs']);

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        SeoCache::forgetSitemap();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('categories', 'slug')->ignore($category?->id),
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:500'],
            'featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ] + $this->seo->seoRules());

        $validated['slug'] = filled($validated['slug'] ?? null)
            ? $validated['slug']
            : $this->autoSeo->uniqueCategorySlug($validated['name'], $category?->id);
        $validated['featured'] = (bool) ($validated['featured'] ?? false);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }
}
