<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\SeoService;
use Illuminate\Database\Seeder;

class ProductContentEnrichmentSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array<string, mixed>> $content */
        $content = require database_path('data/product_content.php');
        $seo = app(SeoService::class);
        $updated = 0;
        $missing = [];

        foreach ($content as $slug => $payload) {
            $product = Product::query()->where('slug', $slug)->first();

            if (! $product) {
                $missing[] = $slug;

                continue;
            }

            $product->forceFill([
                'description' => (string) ($payload['description'] ?? $product->description),
                'details' => array_values($payload['details'] ?? $product->details ?? []),
                'specifications' => array_values($payload['specifications'] ?? $product->specifications ?? []),
            ])->save();

            $seoPayload = $payload['seo'] ?? [];
            if (isset($seoPayload['meta_title']) && ! isset($seoPayload['title'])) {
                $seoPayload['title'] = $seoPayload['meta_title'];
            }
            unset($seoPayload['meta_title']);

            $faqs = collect($payload['faqs'] ?? [])
                ->values()
                ->map(fn (array $faq, int $index) => [
                    'question' => $faq['question'],
                    'answer' => $faq['answer'],
                    'sort_order' => $index,
                    'is_visible' => true,
                ])
                ->all();

            $seo->updateFor($product, $seoPayload, $faqs);
            $updated++;
        }

        $this->command?->info("Enriched {$updated} products with SEO content.");

        if ($missing !== []) {
            $this->command?->warn('Missing product slugs: '.implode(', ', $missing));
        }
    }
}
