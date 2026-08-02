<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Services\SeoSettingsService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SeoAssetController extends Controller
{
    public function sitemap(SeoSettingsService $settings): Response
    {
        abort_unless((bool) ($settings->all()['sitemap']['enabled'] ?? true), 404);

        $base = rtrim((string) config('app.url'), '/') ?: rtrim((string) url('/'), '/');
        $urls = collect([
            ['loc' => $base.'/', 'priority' => '1.0'],
            ['loc' => $base.'/shop', 'priority' => '0.8'],
            ['loc' => $base.'/blog', 'priority' => '0.6'],
            ['loc' => $base.'/about', 'priority' => '0.5'],
            ['loc' => $base.'/contact', 'priority' => '0.5'],
            ['loc' => $base.'/shipping', 'priority' => '0.4'],
            ['loc' => $base.'/returns', 'priority' => '0.4'],
            ['loc' => $base.'/payments', 'priority' => '0.4'],
            ['loc' => $base.'/privacy-policy', 'priority' => '0.3'],
            ['loc' => $base.'/terms', 'priority' => '0.3'],
            ['loc' => $base.'/shopping-confidence-shipping-replacement', 'priority' => '0.4'],
        ]);

        try {
            if (Schema::hasTable('categories')) {
                Category::query()->orderBy('sort_order')->get(['slug', 'updated_at'])->each(function (Category $category) use ($urls, $base) {
                    $urls->push([
                        'loc' => $base.'/category/'.$category->slug,
                        'lastmod' => $category->updated_at?->toAtomString(),
                        'priority' => '0.7',
                    ]);
                });
            }

            if (Schema::hasTable('products')) {
                Product::query()->active()->latest('updated_at')->get(['slug', 'image', 'updated_at'])->each(function (Product $product) use ($urls, $base) {
                    $image = null;
                    if (filled($product->image)) {
                        $image = str_starts_with((string) $product->image, 'http')
                            ? (string) $product->image
                            : $base.'/'.ltrim((string) $product->image, '/');
                    }

                    $urls->push(array_filter([
                        'loc' => $base.'/product/'.$product->slug,
                        'lastmod' => $product->updated_at?->toAtomString(),
                        'priority' => '0.9',
                        'image' => $image,
                    ], fn ($value) => $value !== null && $value !== ''));
                });
            }

            if (Schema::hasTable('posts')) {
                Post::query()->published()->latest('published_at')->get(['slug', 'updated_at', 'published_at'])->each(function (Post $post) use ($urls, $base) {
                    $urls->push([
                        'loc' => $base.'/blog/'.$post->slug,
                        'lastmod' => ($post->updated_at ?: $post->published_at)?->toAtomString(),
                        'priority' => '0.6',
                    ]);
                });
            }
        } catch (Throwable $exception) {
            Log::warning('SEO sitemap generation partially failed.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return response()
            ->view('seo.sitemap', ['urls' => $urls->unique('loc')->values()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(SeoSettingsService $settings): Response
    {
        $data = $settings->all();
        $base = rtrim((string) config('app.url'), '/') ?: rtrim((string) url('/'), '/');
        $disallow = $data['robots']['disallow'] ?? [];

        return response()
            ->view('seo.robots', [
                'enabled' => (bool) ($data['robots']['enabled'] ?? true),
                'disallow' => $disallow,
                'sitemap' => $base.'/sitemap.xml',
            ])
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
