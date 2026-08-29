<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        return BannerResource::collection(
            Banner::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $banner = Banner::query()->create($this->validated($request));

        return (new BannerResource($banner))->response()->setStatusCode(201);
    }

    public function show(Banner $banner)
    {
        return new BannerResource($banner);
    }

    public function update(Request $request, Banner $banner)
    {
        $banner->fill($this->validated($request, updating: true))->save();

        return new BannerResource($banner);
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();

        return response()->json(['message' => 'Banner deleted.']);
    }

    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        $validated = $request->validate([
            'mobile_image' => [$required, 'string', 'max:500'],
            'web_image' => [$required, 'string', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! $updating || array_key_exists('alt_text', $validated)) {
            $validated['alt_text'] = trim((string) ($validated['alt_text'] ?? '')) ?: 'Homepage banner';
        }

        if (! $updating || array_key_exists('sort_order', $validated)) {
            $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        }

        if (! $updating || array_key_exists('is_active', $validated)) {
            $validated['is_active'] = (bool) ($validated['is_active'] ?? true);
        }

        return $validated;
    }
}
