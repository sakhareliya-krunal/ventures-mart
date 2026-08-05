<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class SeoAutoGenerator
{
    public function __construct(private readonly SeoSettingsService $settings)
    {
    }

    public function forProduct(Product $product): array
    {
        $brand = $this->brandName();
        $name = trim((string) $product->name);
        $slug = (string) $product->slug;
        $description = $this->metaDescriptionFromHtml((string) ($product->description ?? ''));
        $categoryName = trim((string) ($product->category?->name ?? ''));
        $keywords = $this->keywords([$name, $categoryName, ...$this->productBrandHints($product)]);
        $title = $this->title($name, $brand);
        $canonical = $this->absoluteUrl('/product/'.$slug);
        $image = $this->absoluteUrl($product->image);
        $robots = ($product->is_active === false) ? 'noindex,follow' : 'index,follow';
        $alt = $categoryName !== '' ? "{$name} — {$categoryName}" : $name;

        return [
            'title' => $title,
            'meta_description' => $description,
            'meta_keywords' => $keywords,
            'focus_keyword' => $categoryName !== '' ? $categoryName : Str::before($name, ' '),
            'seo_slug' => $slug,
            'canonical_url' => $canonical,
            'meta_robots' => $robots,
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image,
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $image,
            'image_alt_text' => $alt,
            'locale' => $this->locale(),
        ];
    }

    public function forCategory(Category $category): array
    {
        $brand = $this->brandName();
        $name = trim((string) $category->name);
        $slug = (string) $category->slug;
        $description = $this->metaDescriptionFromHtml((string) ($category->description ?? ''));
        if ($description === '') {
            $description = $this->limitPlain(
                "Shop {$name} online at {$brand}. Browse quality products with trusted delivery across India.",
                160
            );
        }
        $title = $this->title($name, $brand);
        $canonical = $this->absoluteUrl('/category/'.$slug);
        $image = $this->absoluteUrl($category->image ?: ($this->settings->all()['site']['default_og_image'] ?? null));

        return [
            'title' => $title,
            'meta_description' => $description,
            'meta_keywords' => $this->keywords([$name, $brand]),
            'focus_keyword' => $name,
            'seo_slug' => $slug,
            'canonical_url' => $canonical,
            'meta_robots' => 'index,follow',
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => $image,
            'twitter_title' => $title,
            'twitter_description' => $description,
            'twitter_image' => $image,
            'image_alt_text' => $name,
            'locale' => $this->locale(),
        ];
    }

    /**
     * Fill empty SEO keys from defaults. Filled existing/manual values always win.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>|null  $manual
     * @return array<string, mixed>
     */
    public function merge(array $defaults, array $existing = [], ?array $manual = null): array
    {
        $result = $defaults;

        foreach ($existing as $key => $value) {
            if ($this->filled($value)) {
                $result[$key] = $value;
            }
        }

        if (is_array($manual)) {
            foreach ($manual as $key => $value) {
                if ($this->filled($value)) {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    public function uniqueProductSlug(string $name, ?int $ignoreId = null): string
    {
        return $this->uniqueSlug(
            Product::query(),
            $name,
            $ignoreId,
        );
    }

    public function uniqueCategorySlug(string $name, ?int $ignoreId = null): string
    {
        return $this->uniqueSlug(
            Category::query(),
            $name,
            $ignoreId,
        );
    }

    public function syncSeoSlug(array $payload, string $entitySlug): array
    {
        $payload['seo_slug'] = $entitySlug;

        return $payload;
    }

    private function uniqueSlug($query, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'item';
        $slug = $base;
        $suffix = 2;

        while (
            (clone $query)
                ->when($ignoreId, fn ($builder) => $builder->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function title(string $name, string $brand): string
    {
        $name = trim($name);

        return $name !== '' ? "{$name} | {$brand}" : $brand;
    }

    private function metaDescriptionFromHtml(string $html): string
    {
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/\s+/u', ' ', trim($plain)) ?: '';

        return $this->limitPlain($plain, 160);
    }

    private function limitPlain(string $text, int $max = 160): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }

        $slice = mb_substr($text, 0, $max);
        if (preg_match('/^(.*)\s\S*$/u', $slice, $matches) && mb_strlen($matches[1]) >= 150) {
            return rtrim($matches[1], " \t.,;:-").'…';
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    /**
     * @param  array<int, string|null>  $parts
     */
    private function keywords(array $parts): string
    {
        $seen = [];
        $out = [];
        foreach ($parts as $part) {
            $value = trim((string) $part);
            if ($value === '') {
                continue;
            }
            $key = Str::lower($value);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $value;
        }

        return implode(', ', $out);
    }

    /**
     * @return array<int, string>
     */
    private function productBrandHints(Product $product): array
    {
        $hints = [];
        $tags = is_array($product->tags) ? $product->tags : [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '' || Str::lower($tag) === 'featured') {
                continue;
            }
            $hints[] = $tag;
        }

        return $hints;
    }

    private function brandName(): string
    {
        return (string) ($this->settings->all()['site']['brand_name'] ?? 'Ventures Mart');
    }

    private function locale(): string
    {
        return (string) ($this->settings->all()['site']['default_locale'] ?? 'en-IN');
    }

    private function absoluteUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (preg_match('#^https?://#i', (string) $path)) {
            return (string) $path;
        }

        $base = rtrim((string) config('app.url'), '/') ?: rtrim((string) url('/'), '/');

        return $base.'/'.ltrim((string) $path, '/');
    }

    private function filled(mixed $value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, fn ($item) => $this->filled($item))) > 0;
        }

        return filled($value);
    }
}
