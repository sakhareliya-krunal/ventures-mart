<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\SeoRedirect;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(private readonly SeoService $seo)
    {
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
        $this->seo->updateFor($category, $seoPayload, $faqsPayload);
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
        $this->createSlugRedirect('/category/'.$oldSlug, '/category/'.$category->slug);
        $this->seo->updateFor($category, $seoPayload, $faqsPayload);
        $category->load(['seoMetadata', 'seoFaqs']);

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();

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

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['featured'] = (bool) ($validated['featured'] ?? false);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        return $validated;
    }

    private function createSlugRedirect(string $oldPath, string $newPath): void
    {
        if ($oldPath === $newPath) {
            return;
        }

        SeoRedirect::query()->updateOrCreate(
            ['old_path' => $oldPath],
            ['target_path' => $newPath, 'status_code' => 301, 'is_active' => true],
        );
    }
}
