<?php

namespace App\Services;

use App\Models\SeoSetting;
use Illuminate\Support\Facades\Cache;

class SeoSettingsService
{
    public const CACHE_KEY = 'seo.settings';

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $stored = SeoSetting::query()->pluck('value', 'key')->all();

            return array_replace_recursive($this->defaults(), $stored);
        });
    }

    public function update(array $values): array
    {
        foreach ($values as $key => $value) {
            SeoSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);

        return $this->all();
    }

    public function defaults(): array
    {
        return [
            'site' => [
                'brand_name' => config('app.name', 'Ventures Mart'),
                'tagline' => 'Toys, lunch boxes, and family essentials',
                'default_locale' => env('SEO_DEFAULT_LOCALE', 'en-IN'),
                'default_robots' => 'index,follow',
                'default_og_image' => '/images/ventures-mart-logo.png',
                'logo' => '/images/ventures-mart-logo.png',
                'same_as' => [],
            ],
            'verification' => [
                'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
            ],
            'analytics' => [
                'ga_measurement_id' => env('GA_MEASUREMENT_ID'),
                'gtm_container_id' => env('GTM_CONTAINER_ID'),
            ],
            'robots' => [
                'enabled' => true,
                'disallow' => ['/admin', '/checkout', '/cart', '/profile', '/orders'],
            ],
            'sitemap' => [
                'enabled' => true,
            ],
        ];
    }
}
