<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;

class SchemaBuilder
{
    public function organization(array $settings, string $baseUrl): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $baseUrl.'/#organization',
            'name' => $settings['site']['brand_name'] ?? 'Ventures Mart',
            'url' => $baseUrl,
            'logo' => $this->absoluteUrl($settings['site']['logo'] ?? null, $baseUrl),
            'sameAs' => $settings['site']['same_as'] ?? [],
        ]);
    }

    public function website(array $settings, string $baseUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $baseUrl.'/#website',
            'name' => $settings['site']['brand_name'] ?? 'Ventures Mart',
            'url' => $baseUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $baseUrl.'/search?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public function product(Product $product, string $url, string $baseUrl, ?string $description = null): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $url.'#product',
            'name' => $product->name,
            'sku' => $product->sku,
            'image' => collect($product->localGallery())->map(fn ($image) => $this->absoluteUrl($image, $baseUrl))->values()->all(),
            'description' => $description ?: $this->plainText($product->description),
            'category' => $product->category?->name,
            'brand' => [
                '@type' => 'Brand',
                'name' => config('app.name', 'Ventures Mart'),
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $url,
                'priceCurrency' => 'INR',
                'price' => number_format((float) $product->price, 2, '.', ''),
                'availability' => ((int) $product->stock > 0)
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];

        if ((int) $product->reviews > 0 && (float) $product->rating > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $product->rating,
                'reviewCount' => (int) $product->reviews,
            ];
        }

        return array_filter($schema);
    }

    public function breadcrumb(array $items): ?array
    {
        if (count($items) < 2) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $index) => array_filter([
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['label'] ?? '',
                'item' => $item['url'] ?? null,
            ]))->all(),
        ];
    }

    public function faq(array $faqs): ?array
    {
        $items = collect($faqs)->filter(fn ($faq) => filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))->values();

        if ($items->isEmpty()) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $items->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $this->plainText($faq['answer']),
                ],
            ])->all(),
        ];
    }

    public function blogPosting(Post $post, string $url, string $baseUrl): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            '@id' => $url.'#article',
            'headline' => $post->title,
            'description' => $post->excerpt,
            'image' => $this->absoluteUrl($post->cover_image, $baseUrl),
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'mainEntityOfPage' => $url,
        ]);
    }

    public function collectionPage(Category|string $subject, string $url, ?string $description = null): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            '@id' => $url.'#collection',
            'name' => is_string($subject) ? $subject : $subject->name,
            'description' => $description ?: (is_string($subject) ? null : $subject->description),
            'url' => $url,
        ];
    }

    public function absoluteUrl(?string $path, string $baseUrl): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function plainText(?string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)));
    }
}
