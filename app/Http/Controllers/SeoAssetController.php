<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Services\SeoSettingsService;
use Illuminate\Http\Response;

class SeoAssetController extends Controller
{
    public function sitemap(SeoSettingsService $settings): Response
    {
        abort_unless((bool) ($settings->all()['sitemap']['enabled'] ?? true), 404);

        $base = rtrim((string) config('app.url'), '/');
        $urls = collect([
            ['loc' => $base.'/', 'priority' => '1.0'],
            ['loc' => $base.'/shop', 'priority' => '0.8'],
            ['loc' => $base.'/blog', 'priority' => '0.6'],
            ['loc' => $base.'/about', 'priority' => '0.5'],
            ['loc' => $base.'/contact', 'priority' => '0.5'],
            ['loc' => $base.'/shipping', 'priority' => '0.4'],
            ['loc' => $base.'/returns', 'priority' => '0.4'],
            ['loc' => $base.'/payments', 'priority' => '0.4'],
        ]);

        Category::query()->orderBy('sort_order')->get()->each(function (Category $category) use ($urls, $base) {
            $urls->push([
                'loc' => $base.'/category/'.$category->slug,
                'lastmod' => $category->updated_at?->toAtomString(),
                'priority' => '0.7',
            ]);
        });

        Product::query()->active()->latest('updated_at')->get()->each(function (Product $product) use ($urls, $base) {
            $urls->push([
                'loc' => $base.'/product/'.$product->slug,
                'lastmod' => $product->updated_at?->toAtomString(),
                'priority' => '0.9',
                'image' => $product->image ? $base.'/'.ltrim($product->image, '/') : null,
            ]);
        });

        Post::query()->published()->latest('published_at')->get()->each(function (Post $post) use ($urls, $base) {
            $urls->push([
                'loc' => $base.'/blog/'.$post->slug,
                'lastmod' => ($post->updated_at ?: $post->published_at)?->toAtomString(),
                'priority' => '0.6',
            ]);
        });

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(SeoSettingsService $settings): Response
    {
        $data = $settings->all();
        $base = rtrim((string) config('app.url'), '/');
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
