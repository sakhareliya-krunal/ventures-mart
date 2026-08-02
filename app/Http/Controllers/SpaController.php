<?php

namespace App\Http\Controllers;

use App\Models\SeoRedirect;
use App\Services\SeoService;
use Illuminate\Http\Request;

class SpaController extends Controller
{
    public function __invoke(Request $request, SeoService $seo)
    {
        $path = '/'.trim($request->path(), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');

        $redirect = SeoRedirect::query()
            ->where('old_path', $path)
            ->where('is_active', true)
            ->first();

        if ($redirect) {
            $redirect->forceFill([
                'hit_count' => $redirect->hit_count + 1,
                'last_hit_at' => now(),
            ])->save();

            return redirect($redirect->target_path, $redirect->status_code);
        }

        return view('app', [
            'seo' => $seo->resolvePath($path),
        ]);
    }
}
