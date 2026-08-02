<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\SeoRedirect;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private readonly SeoService $seo)
    {
    }

    public function index(Request $request)
    {
        $query = Product::query()->with(['category', 'seoMetadata'])->latest('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return ProductResource::collection(
            $query->paginate(min((int) $request->integer('per_page', 20), 100))
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $seoPayload = $validated['seo'] ?? null;
        $faqsPayload = $validated['faqs'] ?? null;
        unset($validated['seo'], $validated['faqs']);

        $product = Product::query()->create($validated);
        $this->seo->updateFor($product, $seoPayload, $faqsPayload);
        $product->load(['category', 'seoMetadata', 'seoFaqs']);

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Product $product)
    {
        $product->load(['category', 'seoMetadata', 'seoFaqs']);

        return new ProductResource($product);
    }

    public function update(Request $request, Product $product)
    {
        $oldSlug = $product->slug;
        $validated = $this->validated($request, $product);
        $seoPayload = $validated['seo'] ?? null;
        $faqsPayload = $validated['faqs'] ?? null;
        unset($validated['seo'], $validated['faqs']);

        $product->fill($validated)->save();
        $this->createSlugRedirect('/product/'.$oldSlug, '/product/'.$product->slug);
        $this->seo->updateFor($product, $seoPayload, $faqsPayload);
        $product->load(['category', 'seoMetadata', 'seoFaqs']);

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'slug')->ignore($product?->id),
            ],
            'sku' => [
                'required',
                'string',
                'max:120',
                Rule::unique('products', 'sku')->ignore($product?->id),
            ],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'image' => ['required', 'string', 'max:500'],
            'hover_image' => ['nullable', 'string', 'max:500'],
            'badge' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'stock' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'array'],
            'details' => ['nullable', 'array'],
            'details.*' => ['nullable', 'string', 'max:500'],
            'specifications' => ['nullable', 'array'],
            'specifications.*.label' => ['nullable', 'string', 'max:120'],
            'specifications.*.value' => ['nullable', 'string', 'max:255'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['string', 'max:500'],
            'color_name' => ['nullable', 'string', 'max:80'],
            'color_hex' => ['nullable', 'string', 'max:20'],
            'variant_group_id' => ['nullable', 'string', 'max:120'],
            'external_id' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('products', 'external_id')->ignore($product?->id),
            ],
        ] + $this->seo->seoRules());

        $validated['slug'] = filled($validated['slug'] ?? null)
            ? $validated['slug']
            : Str::slug($validated['name']).'-'.Str::lower(Str::random(4));

        if (! $product) {
            $validated['external_id'] = filled($validated['external_id'] ?? null)
                ? $validated['external_id']
                : 'prod-'.Str::lower(Str::random(10));
            $validated['is_active'] = array_key_exists('is_active', $validated)
                ? (bool) $validated['is_active']
                : true;
        } elseif (array_key_exists('external_id', $validated) && blank($validated['external_id'])) {
            unset($validated['external_id']);
        }

        if (array_key_exists('featured', $validated)) {
            $featured = (bool) $validated['featured'];
            unset($validated['featured']);
            $tags = array_values(array_filter(
                $validated['tags'] ?? ($product?->tags ?? []),
                fn ($tag) => $tag !== 'featured'
            ));
            if ($featured) {
                $tags[] = 'featured';
                if (! filled($validated['badge'] ?? null)) {
                    $validated['badge'] = 'Featured';
                }
            } elseif (($validated['badge'] ?? $product?->badge) === 'Featured') {
                $validated['badge'] = null;
            }
            $validated['tags'] = $tags;
        }

        foreach (['image', 'description', 'hover_image', 'badge'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] === null) {
                $validated[$field] = $field === 'hover_image' || $field === 'badge' ? null : '';
            }
        }

        if (! array_key_exists('description', $validated) || $validated['description'] === null) {
            $validated['description'] = '';
        }

        if (array_key_exists('gallery', $validated) && is_array($validated['gallery'])) {
            $validated['gallery'] = array_values(array_filter(
                $validated['gallery'],
                fn ($path) => is_string($path) && $path !== ''
            ));
        }

        if (array_key_exists('details', $validated) && is_array($validated['details'])) {
            $validated['details'] = array_values(array_filter(
                array_map(fn ($item) => is_string($item) ? trim($item) : '', $validated['details']),
                fn ($item) => $item !== ''
            ));
        }

        if (array_key_exists('specifications', $validated) && is_array($validated['specifications'])) {
            $validated['specifications'] = array_values(array_filter(
                array_map(function ($row) {
                    if (! is_array($row)) {
                        return null;
                    }

                    $label = trim((string) ($row['label'] ?? ''));
                    $value = trim((string) ($row['value'] ?? ''));

                    if ($label === '' || $value === '') {
                        return null;
                    }

                    return ['label' => $label, 'value' => $value];
                }, $validated['specifications']),
            ));
        }

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
