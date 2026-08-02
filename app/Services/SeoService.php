<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\SeoFaq;
use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SeoService
{
    public function __construct(
        private readonly SeoSettingsService $settings,
        private readonly SchemaBuilder $schema,
        private readonly SeoScoreService $score,
    ) {
    }

    public function serializeForResource(Model|string $subject, ?string $pageKey = null): array
    {
        $seo = $this->metadataFor($subject, $pageKey);
        $faqs = $this->faqsFor($subject);

        return [
            'metadata' => $this->metadataPayload($seo),
            'faqs' => $faqs,
            'score' => $this->score->score($seo, $subject, $faqs),
            'suggested_links' => $this->internalLinks($subject),
        ];
    }

    public function updateFor(Model|string $subject, ?array $seoPayload, ?array $faqsPayload = null, ?string $pageKey = null): ?SeoMetadata
    {
        if ($seoPayload === null && $faqsPayload === null) {
            return $this->metadataFor($subject, $pageKey);
        }

        $locale = $seoPayload['locale'] ?? $this->locale();
        $seo = $this->metadataFor($subject, $pageKey, $locale) ?? new SeoMetadata(['locale' => $locale]);

        if ($subject instanceof Model && ! $seo->exists) {
            $seo->seoable()->associate($subject);
        } elseif (is_string($subject) && ! $seo->exists) {
            $seo->page_key = $pageKey ?: $subject;
        }

        if ($seoPayload !== null) {
            $seo->fill($this->cleanSeoPayload($seoPayload));
        }

        $faqs = $faqsPayload !== null ? $this->syncFaqs($subject, $faqsPayload) : $this->faqsFor($subject);
        $seo->score = $this->score->score($seo, $subject, $faqs);
        $seo->scored_at = now();
        $seo->save();

        return $seo;
    }

    public function resolvePath(string $path): array
    {
        $normalized = '/'.trim($path, '/');
        $normalized = $normalized === '/' ? '/' : rtrim($normalized, '/');
        $baseUrl = $this->baseUrl();
        $settings = $this->settings->all();

        [$subject, $pageKey, $type] = $this->subjectForPath($normalized);
        $seo = $this->metadataFor($subject ?? $pageKey, $pageKey);
        $faqs = $subject ? $this->faqsFor($subject) : $this->faqsFor($pageKey);
        $fallback = $this->fallback($subject, $pageKey, $type, $normalized);
        $title = $this->first($seo?->title, $fallback['title']);
        $description = $this->limit($this->first($seo?->meta_description, $fallback['description']), 165);
        $canonical = $this->canonical($seo?->canonical_url, $normalized);
        $robots = $this->first($seo?->meta_robots, $settings['site']['default_robots'] ?? 'index,follow');
        $image = $this->absoluteUrl($this->first($seo?->og_image, $seo?->twitter_image, $fallback['image'], $settings['site']['default_og_image'] ?? null));
        $breadcrumbs = $this->breadcrumbs($subject, $type, $normalized);
        $schemas = [
            $this->schema->organization($settings, $baseUrl),
            $this->schema->website($settings, $baseUrl),
            $this->schema->breadcrumb($breadcrumbs),
            $this->schemaForSubject($subject, $type, $canonical, $description),
            $this->schema->faq($faqs),
            $seo?->custom_schema,
        ];

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'locale' => $seo?->locale ?: $this->locale(),
            'hreflang' => [
                ['locale' => $seo?->locale ?: $this->locale(), 'url' => $canonical],
            ],
            'og' => [
                'title' => $this->first($seo?->og_title, $title),
                'description' => $this->first($seo?->og_description, $description),
                'image' => $image,
                'url' => $canonical,
                'type' => $type === 'post' ? 'article' : 'website',
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'title' => $this->first($seo?->twitter_title, $seo?->og_title, $title),
                'description' => $this->first($seo?->twitter_description, $seo?->og_description, $description),
                'image' => $this->absoluteUrl($this->first($seo?->twitter_image, $image)),
            ],
            'verification' => $settings['verification'] ?? [],
            'analytics' => $settings['analytics'] ?? [],
            'json_ld' => array_values(array_filter($schemas)),
            'breadcrumbs' => $breadcrumbs,
            'score' => $this->score->score($seo, $subject ?? $pageKey, $faqs),
        ];
    }

    public function metadataPayload(?SeoMetadata $seo): array
    {
        return [
            'title' => $seo?->title,
            'meta_description' => $seo?->meta_description,
            'focus_keyword' => $seo?->focus_keyword,
            'seo_slug' => $seo?->seo_slug,
            'canonical_url' => $seo?->canonical_url,
            'meta_robots' => $seo?->meta_robots ?? 'index,follow',
            'og_title' => $seo?->og_title,
            'og_description' => $seo?->og_description,
            'og_image' => $seo?->og_image,
            'twitter_title' => $seo?->twitter_title,
            'twitter_description' => $seo?->twitter_description,
            'twitter_image' => $seo?->twitter_image,
            'image_alt_text' => $seo?->image_alt_text,
            'ai_summary' => $seo?->ai_summary,
            'ai_highlights' => $seo?->ai_highlights ?? [],
            'custom_schema' => $seo?->custom_schema,
            'locale' => $seo?->locale ?? $this->locale(),
        ];
    }

    public function seoRules(string $prefix = 'seo'): array
    {
        return [
            "{$prefix}" => ['nullable', 'array'],
            "{$prefix}.title" => ['nullable', 'string', 'max:255'],
            "{$prefix}.meta_description" => ['nullable', 'string', 'max:500'],
            "{$prefix}.focus_keyword" => ['nullable', 'string', 'max:120'],
            "{$prefix}.seo_slug" => ['nullable', 'string', 'max:255'],
            "{$prefix}.canonical_url" => ['nullable', 'string', 'max:500'],
            "{$prefix}.meta_robots" => ['nullable', 'string', 'max:120'],
            "{$prefix}.og_title" => ['nullable', 'string', 'max:255'],
            "{$prefix}.og_description" => ['nullable', 'string', 'max:500'],
            "{$prefix}.og_image" => ['nullable', 'string', 'max:500'],
            "{$prefix}.twitter_title" => ['nullable', 'string', 'max:255'],
            "{$prefix}.twitter_description" => ['nullable', 'string', 'max:500'],
            "{$prefix}.twitter_image" => ['nullable', 'string', 'max:500'],
            "{$prefix}.image_alt_text" => ['nullable', 'string', 'max:255'],
            "{$prefix}.ai_summary" => ['nullable', 'string', 'max:2000'],
            "{$prefix}.ai_highlights" => ['nullable', 'array'],
            "{$prefix}.ai_highlights.*" => ['nullable', 'string', 'max:255'],
            "{$prefix}.custom_schema" => ['nullable', 'array'],
            "{$prefix}.locale" => ['nullable', 'string', 'max:12'],
            'faqs' => ['nullable', 'array'],
            'faqs.*.question' => ['required_with:faqs', 'string', 'max:255'],
            'faqs.*.answer' => ['required_with:faqs', 'string', 'max:2000'],
            'faqs.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'faqs.*.is_visible' => ['nullable', 'boolean'],
        ];
    }

    public function cleanSeoPayload(array $payload): array
    {
        $allowed = [
            'locale',
            'title',
            'meta_description',
            'focus_keyword',
            'seo_slug',
            'canonical_url',
            'meta_robots',
            'og_title',
            'og_description',
            'og_image',
            'twitter_title',
            'twitter_description',
            'twitter_image',
            'image_alt_text',
            'ai_summary',
            'ai_highlights',
            'custom_schema',
        ];

        $clean = Arr::only($payload, $allowed);
        $clean['locale'] = $clean['locale'] ?? $this->locale();
        $clean['meta_robots'] = $clean['meta_robots'] ?? 'index,follow';
        $clean['ai_highlights'] = array_values(array_filter($clean['ai_highlights'] ?? [], fn ($item) => filled($item)));

        return $clean;
    }

    public function faqsFor(Model|string|null $subject): array
    {
        if (is_string($subject)) {
            return SeoFaq::query()
                ->where('page_key', $subject)
                ->where('is_visible', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (SeoFaq $faq) => [
                    'id' => $faq->id,
                    'question' => $faq->question,
                    'answer' => $faq->answer,
                    'sort_order' => $faq->sort_order,
                    'is_visible' => $faq->is_visible,
                ])
                ->values()
                ->all();
        }

        if (! $subject instanceof Model || ! method_exists($subject, 'seoFaqs')) {
            return [];
        }

        $relation = $subject->relationLoaded('seoFaqs')
            ? $subject->seoFaqs
            : $subject->seoFaqs()->get();

        return $relation
            ->filter(fn (SeoFaq $faq) => $faq->is_visible)
            ->map(fn (SeoFaq $faq) => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'sort_order' => $faq->sort_order,
                'is_visible' => $faq->is_visible,
            ])
            ->values()
            ->all();
    }

    public function internalLinks(Model|string|null $subject): array
    {
        $links = collect([
            ['label' => 'Shop all products', 'url' => '/shop'],
            ['label' => 'Shipping information', 'url' => '/shipping'],
            ['label' => 'Returns and replacement', 'url' => '/returns'],
        ]);

        if ($subject instanceof Product && $subject->category) {
            $links->prepend(['label' => $subject->category->name, 'url' => '/category/'.$subject->category->slug]);
        }

        if ($subject instanceof Category) {
            Product::query()
                ->active()
                ->where('category_id', $subject->id)
                ->latest('id')
                ->limit(3)
                ->get(['name', 'slug'])
                ->each(fn (Product $product) => $links->push([
                    'label' => $product->name,
                    'url' => '/product/'.$product->slug,
                ]));
        }

        return $links->unique('url')->values()->all();
    }

    private function metadataFor(Model|string|null $subject, ?string $pageKey = null, ?string $locale = null): ?SeoMetadata
    {
        $locale ??= $this->locale();

        if ($subject instanceof Model && method_exists($subject, 'seoMetadata')) {
            return $subject->relationLoaded('seoMetadata')
                ? $subject->seoMetadata
                : $subject->seoMetadata()->where('locale', $locale)->first();
        }

        $key = $pageKey ?: (is_string($subject) ? $subject : null);

        if (! $key) {
            return null;
        }

        return SeoMetadata::query()->where('page_key', $key)->where('locale', $locale)->first();
    }

    private function syncFaqs(Model|string $subject, array $payload): array
    {
        if (is_string($subject)) {
            SeoFaq::query()->where('page_key', $subject)->delete();

            foreach (array_values($payload) as $index => $faq) {
                if (! filled($faq['question'] ?? null) || ! filled($faq['answer'] ?? null)) {
                    continue;
                }

                SeoFaq::query()->create([
                    'page_key' => $subject,
                    'locale' => $faq['locale'] ?? $this->locale(),
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                    'sort_order' => $faq['sort_order'] ?? $index,
                    'is_visible' => $faq['is_visible'] ?? true,
                ]);
            }

            return $this->faqsFor($subject);
        }

        if (! $subject instanceof Model || ! method_exists($subject, 'seoFaqs')) {
            return [];
        }

        $subject->seoFaqs()->delete();

        foreach (array_values($payload) as $index => $faq) {
            if (! filled($faq['question'] ?? null) || ! filled($faq['answer'] ?? null)) {
                continue;
            }

            $subject->seoFaqs()->create([
                'locale' => $faq['locale'] ?? $this->locale(),
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'sort_order' => $faq['sort_order'] ?? $index,
                'is_visible' => $faq['is_visible'] ?? true,
            ]);
        }

        $subject->load('seoFaqs');

        return $this->faqsFor($subject);
    }

    private function subjectForPath(string $path): array
    {
        if ($path === '/') {
            return [null, 'home', 'home'];
        }

        if (preg_match('#^/product/([^/]+)$#', $path, $matches)) {
            return [Product::query()->with(['category', 'seoMetadata', 'seoFaqs'])->active()->where('slug', $matches[1])->first(), null, 'product'];
        }

        if (preg_match('#^/category/([^/]+)$#', $path, $matches)) {
            return [Category::query()->with(['seoMetadata', 'seoFaqs'])->where('slug', $matches[1])->first(), null, 'category'];
        }

        if (preg_match('#^/blog/([^/]+)$#', $path, $matches)) {
            return [Post::query()->with(['seoMetadata', 'seoFaqs'])->published()->where('slug', $matches[1])->first(), null, 'post'];
        }

        $static = [
            '/shop' => 'shop',
            '/search' => 'search',
            '/blog' => 'blog',
            '/about' => 'about',
            '/contact' => 'contact',
            '/privacy-policy' => 'privacy-policy',
            '/terms' => 'terms',
            '/shipping' => 'shipping',
            '/returns' => 'returns',
            '/payments' => 'payments',
            '/shopping-confidence-shipping-replacement' => 'shopping-confidence-shipping-replacement',
        ];

        return [null, $static[$path] ?? 'not-found', isset($static[$path]) ? 'static' : 'not-found'];
    }

    private function fallback(Model|string|null $subject, ?string $pageKey, string $type, string $path): array
    {
        $brand = $this->settings->all()['site']['brand_name'] ?? 'Ventures Mart';

        if ($subject instanceof Product) {
            $description = $this->plainText($subject->description) ?: "Shop {$subject->name} at {$brand}.";

            return [
                'title' => "{$subject->name} | {$brand}",
                'description' => $description,
                'image' => $subject->image,
            ];
        }

        if ($subject instanceof Category) {
            return [
                'title' => "{$subject->name} | {$brand}",
                'description' => $subject->description ?: "Shop {$subject->name} online at {$brand}.",
                'image' => $subject->image,
            ];
        }

        if ($subject instanceof Post) {
            return [
                'title' => "{$subject->title} | {$brand}",
                'description' => $subject->excerpt,
                'image' => $subject->cover_image,
            ];
        }

        $titles = [
            'home' => "{$brand} | Toys & lunch boxes",
            'shop' => "Shop toys and lunch boxes | {$brand}",
            'blog' => "Blog | {$brand}",
            'search' => "Search | {$brand}",
        ];

        return [
            'title' => $titles[$pageKey] ?? Str::headline((string) $pageKey).' | '.$brand,
            'description' => 'Shop toys, lunch boxes, and family essentials online at '.$brand.'.',
            'image' => $this->settings->all()['site']['default_og_image'] ?? null,
        ];
    }

    private function breadcrumbs(Model|string|null $subject, string $type, string $path): array
    {
        $home = ['label' => 'Home', 'url' => $this->baseUrl().'/'];

        if ($subject instanceof Product) {
            return array_values(array_filter([
                $home,
                ['label' => 'Shop', 'url' => $this->absoluteUrl('/shop')],
                $subject->category ? ['label' => $subject->category->name, 'url' => $this->absoluteUrl('/category/'.$subject->category->slug)] : null,
                ['label' => $subject->name, 'url' => $this->absoluteUrl($path)],
            ]));
        }

        if ($subject instanceof Category) {
            return [$home, ['label' => $subject->name, 'url' => $this->absoluteUrl($path)]];
        }

        if ($subject instanceof Post) {
            return [$home, ['label' => 'Blog', 'url' => $this->absoluteUrl('/blog')], ['label' => $subject->title, 'url' => $this->absoluteUrl($path)]];
        }

        return [$home, ['label' => Str::headline(trim($path, '/') ?: 'Home'), 'url' => $this->absoluteUrl($path)]];
    }

    private function schemaForSubject(Model|string|null $subject, string $type, string $url, string $description): ?array
    {
        if ($subject instanceof Product) {
            return $this->schema->product($subject, $url, $this->baseUrl(), $description);
        }

        if ($subject instanceof Category) {
            return $this->schema->collectionPage($subject, $url, $description);
        }

        if ($subject instanceof Post) {
            return $this->schema->blogPosting($subject, $url, $this->baseUrl());
        }

        if (in_array($type, ['home', 'static'], true)) {
            return $this->schema->collectionPage(Str::headline($type), $url, $description);
        }

        return null;
    }

    private function canonical(?string $custom, string $path): string
    {
        if (filled($custom)) {
            return $this->absoluteUrl($custom);
        }

        return $this->absoluteUrl($path);
    }

    private function absoluteUrl(?string $path): string
    {
        if (! filled($path)) {
            return $this->baseUrl().'/';
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        return rtrim($this->baseUrl(), '/').'/'.ltrim($path, '/');
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/') ?: url('/');
    }

    private function locale(): string
    {
        return $this->settings->all()['site']['default_locale'] ?? 'en-IN';
    }

    private function first(...$values): string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        return '';
    }

    private function limit(string $value, int $limit): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $value)), $limit, '');
    }

    private function plainText(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));
    }
}
