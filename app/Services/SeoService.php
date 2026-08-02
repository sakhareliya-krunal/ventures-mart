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

        $evaluation = $this->score->evaluate($seo, $subject, $faqs);

        return [
            'metadata' => $this->metadataPayload($seo),
            'faqs' => $faqs,
            'score' => $evaluation['score'],
            'checks' => $evaluation['checks'],
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
        $evaluation = $this->score->evaluate($seo, $subject, $faqs);
        $seo->score = $evaluation['score'];
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
        $fallback = $this->fallback($subject, $pageKey, $type, $normalized, $seo);
        $title = $this->first($seo?->title, $fallback['title']);
        $description = $this->limit($this->first($seo?->meta_description, $seo?->ai_summary, $fallback['description']), 165);
        $canonical = $this->canonical($seo?->canonical_url, $normalized);
        $defaultRobots = $type === 'private' || $type === 'not-found'
            ? 'noindex,follow'
            : ($settings['site']['default_robots'] ?? 'index,follow');
        $robots = $this->first($seo?->meta_robots, $defaultRobots);
        if ($type === 'private' || $type === 'not-found') {
            $robots = 'noindex,follow';
        }
        $image = $this->absoluteUrl($this->first($seo?->og_image, $seo?->twitter_image, $fallback['image'], $settings['site']['default_og_image'] ?? null));
        $breadcrumbs = $this->breadcrumbs($subject, $type, $normalized);
        $locale = $seo?->locale ?: $this->locale();
        $schemas = [
            $this->schema->organization($settings, $baseUrl),
            $this->schema->website($settings, $baseUrl),
            $this->schema->breadcrumb($breadcrumbs),
            $this->schemaForSubject($subject, $type, $canonical, $description, $title, $settings),
            $this->schema->faq($faqs),
            $seo?->custom_schema,
        ];

        $ogType = match ($type) {
            'post' => 'article',
            'product' => 'product',
            default => 'website',
        };

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'locale' => $locale,
            'og_locale' => str_replace('-', '_', $locale),
            'hreflang' => [
                ['locale' => $locale, 'url' => $canonical],
                ['locale' => 'x-default', 'url' => $canonical],
            ],
            'og' => [
                'title' => $this->first($seo?->og_title, $title),
                'description' => $this->first($seo?->og_description, $description),
                'image' => $image,
                'url' => $canonical,
                'type' => $ogType,
                'locale' => str_replace('-', '_', $locale),
                'site_name' => $settings['site']['brand_name'] ?? 'Ventures Mart',
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
            'brand_name' => $settings['site']['brand_name'] ?? 'Ventures Mart',
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

        if ($subject instanceof Product) {
            if ($subject->category) {
                $links->prepend([
                    'label' => $subject->category->name,
                    'url' => '/category/'.$subject->category->slug,
                ]);

                Product::query()
                    ->active()
                    ->where('category_id', $subject->category_id)
                    ->where('id', '!=', $subject->id)
                    ->when(
                        $subject->variant_group_id,
                        fn ($query) => $query->where(function ($inner) use ($subject) {
                            $inner->whereNull('variant_group_id')
                                ->orWhere('variant_group_id', '!=', $subject->variant_group_id);
                        })
                    )
                    ->orderByDesc('rating')
                    ->orderByDesc('id')
                    ->limit(3)
                    ->get(['name', 'slug'])
                    ->each(fn (Product $product) => $links->push([
                        'label' => $product->name,
                        'url' => '/product/'.$product->slug,
                    ]));
            }

            $this->relatedBlogPostsForProduct($subject)
                ->each(fn (Post $post) => $links->push([
                    'label' => $post->title,
                    'url' => '/blog/'.$post->slug,
                ]));
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

            $links->push(['label' => 'Ventures Mart blog', 'url' => '/blog']);
        }

        if ($subject instanceof Post) {
            $category = $this->categoryHintForPost($subject);

            if ($category) {
                $links->prepend([
                    'label' => $category->name,
                    'url' => '/category/'.$category->slug,
                ]);

                Product::query()
                    ->active()
                    ->where('category_id', $category->id)
                    ->orderByDesc('rating')
                    ->orderByDesc('id')
                    ->limit(3)
                    ->get(['name', 'slug'])
                    ->each(fn (Product $product) => $links->push([
                        'label' => $product->name,
                        'url' => '/product/'.$product->slug,
                    ]));
            } else {
                Product::query()
                    ->active()
                    ->orderByDesc('rating')
                    ->orderByDesc('id')
                    ->limit(3)
                    ->get(['name', 'slug'])
                    ->each(fn (Product $product) => $links->push([
                        'label' => $product->name,
                        'url' => '/product/'.$product->slug,
                    ]));
            }

            $links->push(['label' => 'Ventures Mart blog', 'url' => '/blog']);
        }

        return $links->unique('url')->values()->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Post>
     */
    private function relatedBlogPostsForProduct(Product $product): \Illuminate\Support\Collection
    {
        $terms = collect([
            $product->seoMetadata?->focus_keyword,
            $product->category?->name,
            $product->category?->slug,
            $product->name,
        ])
            ->filter(fn ($term) => filled($term))
            ->map(fn ($term) => Str::lower(trim((string) $term)))
            ->unique()
            ->values();

        $query = Post::query()->published()->latest('published_at');

        if ($terms->isNotEmpty()) {
            $matched = (clone $query)
                ->where(function ($builder) use ($terms) {
                    foreach ($terms as $term) {
                        $like = '%'.$term.'%';
                        $builder->orWhereRaw('LOWER(title) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(excerpt) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(body) LIKE ?', [$like]);
                    }
                })
                ->limit(2)
                ->get(['title', 'slug']);

            if ($matched->isNotEmpty()) {
                return $matched;
            }
        }

        return $query->limit(2)->get(['title', 'slug']);
    }

    private function categoryHintForPost(Post $post): ?Category
    {
        $haystack = Str::lower(trim(
            implode(' ', array_filter([
                $post->title,
                $post->excerpt,
                strip_tags((string) $post->body),
            ]))
        ));

        $slug = null;
        if (Str::contains($haystack, ['lunch', 'tiffin', 'steel lunch', 'school lunch'])) {
            $slug = 'lunch-box';
        } elseif (Str::contains($haystack, ['toy', 'play', 'blocks', 'plush', 'kitchen play'])) {
            $slug = 'toys';
        }

        if (! $slug) {
            return null;
        }

        return Category::query()->where('slug', $slug)->first();
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

        if (isset($static[$path])) {
            return [null, $static[$path], 'static'];
        }

        $private = [
            '/cart' => 'cart',
            '/checkout' => 'checkout',
            '/wishlist' => 'wishlist',
            '/login' => 'login',
            '/register' => 'register',
            '/profile' => 'profile',
            '/forgot-password' => 'forgot-password',
            '/reset-password' => 'reset-password',
        ];

        if (isset($private[$path]) || str_starts_with($path, '/orders') || str_starts_with($path, '/admin')) {
            $key = $private[$path] ?? (str_starts_with($path, '/orders') ? 'orders' : 'admin');

            return [null, $key, 'private'];
        }

        return [null, 'not-found', 'not-found'];
    }

    private function fallback(
        Model|string|null $subject,
        ?string $pageKey,
        string $type,
        string $path,
        ?SeoMetadata $seo = null,
    ): array {
        $brand = $this->settings->all()['site']['brand_name'] ?? 'Ventures Mart';

        if ($subject instanceof Product) {
            $keyword = trim((string) ($seo?->focus_keyword ?? ''));
            if ($keyword === '') {
                $keyword = trim((string) ($subject->category?->name ?? '')) ?: 'Online';
            }

            $description = $this->plainText($subject->description)
                ?: "Shop {$subject->name} online at {$brand}.";

            return [
                'title' => "{$subject->name} | {$keyword} | {$brand}",
                'description' => $description,
                'image' => $subject->image,
            ];
        }

        if ($subject instanceof Category) {
            return [
                'title' => "{$subject->name} Online | {$brand}",
                'description' => $subject->description ?: "Shop {$subject->name} online at {$brand}.",
                'image' => $subject->image,
            ];
        }

        if ($subject instanceof Post) {
            return [
                'title' => "{$subject->title} | {$brand}",
                'description' => $subject->excerpt ?: "Read {$subject->title} on the {$brand} blog.",
                'image' => $subject->cover_image,
            ];
        }

        $titles = [
            'home' => "{$brand} | Premium Stainless Steel Lunch Boxes Online in India",
            'shop' => "Shop Toys & Lunch Boxes Online | {$brand}",
            'blog' => "Blog | {$brand}",
            'about' => "About | {$brand}",
            'contact' => "Contact | {$brand}",
            'shipping' => "Shipping | {$brand}",
            'returns' => "Returns | {$brand}",
            'payments' => "Payments | {$brand}",
            'privacy-policy' => "Privacy Policy | {$brand}",
            'terms' => "Terms of Service | {$brand}",
            'shopping-confidence-shipping-replacement' => "Shopping with Confidence | {$brand}",
            'search' => "Search | {$brand}",
            'cart' => "Cart | {$brand}",
            'checkout' => "Checkout | {$brand}",
            'wishlist' => "Wishlist | {$brand}",
            'login' => "Sign in | {$brand}",
            'register' => "Create account | {$brand}",
            'profile' => "Profile | {$brand}",
            'orders' => "Orders | {$brand}",
            'admin' => "Admin | {$brand}",
            'not-found' => "Page not found | {$brand}",
        ];

        $descriptions = [
            'home' => 'Buy premium stainless steel lunch boxes for office, school and kids. Leak-proof, BPA-free and durable lunch boxes with fast delivery across India.',
            'shop' => "Shop premium stainless steel lunch boxes and kids toys online at {$brand}. Fast delivery across India.",
            'blog' => "Guides and tips on kids toys, school lunches, and stainless steel lunch boxes from {$brand}.",
            'about' => "Learn about {$brand}—premium stainless steel lunch boxes and curated toys for families across India.",
            'contact' => "Contact {$brand} for order help, product questions, and support across India.",
            'shipping' => "Delivery across India for toys and lunch boxes from {$brand}.",
            'returns' => "7-day replacement support for toys and lunch boxes at {$brand}.",
            'payments' => "Secure online payments and COD options for shopping at {$brand}.",
            'privacy-policy' => "How {$brand} collects and uses information when you browse or shop.",
            'terms' => "Terms for shopping toys and lunch boxes on {$brand} across India.",
            'shopping-confidence-shipping-replacement' => "How delivery across India, free shipping, 7-day replacement, and secure payments work at {$brand}.",
            'cart' => "Review your {$brand} cart.",
            'checkout' => "Secure checkout for toys and lunch boxes at {$brand}.",
            'wishlist' => "Saved products at {$brand}.",
            'login' => "Sign in to your {$brand} account.",
            'register' => "Create your {$brand} account.",
            'profile' => "Manage your {$brand} profile.",
            'orders' => "Track and manage your {$brand} orders.",
            'not-found' => "That page is not part of the {$brand} storefront.",
        ];

        return [
            'title' => $titles[$pageKey] ?? Str::headline((string) $pageKey).' | '.$brand,
            'description' => $descriptions[$pageKey] ?? 'Shop toys, lunch boxes, and family essentials online at '.$brand.'.',
            'image' => $this->settings->all()['site']['default_og_image'] ?? null,
        ];
    }

    private function breadcrumbs(Model|string|null $subject, string $type, string $path): array
    {
        $home = ['label' => 'Home', 'url' => $this->baseUrl().'/'];

        if ($type === 'home' || $path === '/') {
            return [$home];
        }

        if ($type === 'private' || $type === 'not-found') {
            return [$home];
        }

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

    private function schemaForSubject(
        Model|string|null $subject,
        string $type,
        string $url,
        string $description,
        string $title = '',
        array $settings = [],
    ): ?array {
        if ($subject instanceof Product) {
            return $this->schema->product($subject, $url, $this->baseUrl(), $description, $settings);
        }

        if ($subject instanceof Category) {
            return $this->schema->collectionPage($subject, $url, $description);
        }

        if ($subject instanceof Post) {
            return $this->schema->blogPosting($subject, $url, $this->baseUrl(), $settings);
        }

        if (in_array($type, ['home', 'static'], true)) {
            $name = $title !== '' ? preg_replace('/\s*\|\s*.*$/', '', $title) : Str::headline((string) ($type === 'home' ? 'Home' : trim(parse_url($url, PHP_URL_PATH) ?: 'Page', '/')));

            return $this->schema->collectionPage($name ?: 'Ventures Mart', $url, $description);
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
