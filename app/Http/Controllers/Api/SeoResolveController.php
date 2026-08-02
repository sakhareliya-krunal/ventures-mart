<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SeoService;
use Illuminate\Http\Request;

class SeoResolveController extends Controller
{
    public function __invoke(Request $request, SeoService $seo)
    {
        $path = (string) $request->query('path', '/');

        return response()->json($seo->resolvePath($path));
    }
}
