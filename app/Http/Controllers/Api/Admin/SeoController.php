<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoRedirect;
use App\Services\SeoService;
use App\Services\SeoSettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeoController extends Controller
{
    public function settings(SeoSettingsService $settings)
    {
        return response()->json($settings->all());
    }

    public function updateSettings(Request $request, SeoSettingsService $settings)
    {
        $validated = $request->validate([
            'site' => ['sometimes', 'array'],
            'site.brand_name' => ['nullable', 'string', 'max:120'],
            'site.tagline' => ['nullable', 'string', 'max:255'],
            'site.default_locale' => ['nullable', 'string', 'max:12'],
            'site.default_robots' => ['nullable', 'string', 'max:120'],
            'site.default_og_image' => ['nullable', 'string', 'max:500'],
            'site.logo' => ['nullable', 'string', 'max:500'],
            'site.same_as' => ['nullable', 'array'],
            'site.same_as.*' => ['nullable', 'url', 'max:500'],
            'verification' => ['sometimes', 'array'],
            'verification.google_site_verification' => ['nullable', 'string', 'max:255'],
            'analytics' => ['sometimes', 'array'],
            'analytics.ga_measurement_id' => ['nullable', 'string', 'max:80'],
            'analytics.gtm_container_id' => ['nullable', 'string', 'max:80'],
            'robots' => ['sometimes', 'array'],
            'robots.enabled' => ['sometimes', 'boolean'],
            'robots.disallow' => ['nullable', 'array'],
            'robots.disallow.*' => ['nullable', 'string', 'max:255'],
            'sitemap' => ['sometimes', 'array'],
            'sitemap.enabled' => ['sometimes', 'boolean'],
        ]);

        return response()->json($settings->update($validated));
    }

    public function page(string $key, SeoService $seo)
    {
        return response()->json($seo->serializeForResource($key, $key));
    }

    public function updatePage(Request $request, string $key, SeoService $seo)
    {
        $validated = $request->validate($seo->seoRules());
        $seo->updateFor($key, $validated['seo'] ?? [], $validated['faqs'] ?? [], $key);

        return response()->json($seo->serializeForResource($key, $key));
    }

    public function redirects(Request $request)
    {
        $query = SeoRedirect::query()->latest('id');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn ($builder) => $builder
                ->where('old_path', 'like', "%{$search}%")
                ->orWhere('target_path', 'like', "%{$search}%"));
        }

        return response()->json($query->paginate(min((int) $request->integer('per_page', 50), 100)));
    }

    public function storeRedirect(Request $request)
    {
        $validated = $request->validate([
            'old_path' => ['required', 'string', 'max:500', 'different:target_path', 'unique:seo_redirects,old_path'],
            'target_path' => ['required', 'string', 'max:500'],
            'status_code' => ['nullable', 'integer', Rule::in([301, 302, 307, 308])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $redirect = SeoRedirect::query()->create($this->cleanRedirect($validated));

        return response()->json($redirect, 201);
    }

    public function updateRedirect(Request $request, SeoRedirect $redirect)
    {
        $validated = $request->validate([
            'old_path' => ['required', 'string', 'max:500', 'different:target_path', Rule::unique('seo_redirects', 'old_path')->ignore($redirect->id)],
            'target_path' => ['required', 'string', 'max:500'],
            'status_code' => ['nullable', 'integer', Rule::in([301, 302, 307, 308])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $redirect->fill($this->cleanRedirect($validated))->save();

        return response()->json($redirect);
    }

    public function destroyRedirect(SeoRedirect $redirect)
    {
        $redirect->delete();

        return response()->json(['message' => 'Redirect deleted.']);
    }

    private function cleanRedirect(array $payload): array
    {
        return [
            'old_path' => $this->normalizePath($payload['old_path']),
            'target_path' => $this->normalizePath($payload['target_path']),
            'status_code' => $payload['status_code'] ?? 301,
            'is_active' => $payload['is_active'] ?? true,
        ];
    }

    private function normalizePath(string $path): string
    {
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $normalized = '/'.trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
}
