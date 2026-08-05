<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SeoCache
{
    public const SITEMAP_KEY = 'seo.sitemap.payload';

    public const SITEMAP_TTL_SECONDS = 600;

    public static function forgetSitemap(): void
    {
        Cache::forget(self::SITEMAP_KEY);
    }
}
