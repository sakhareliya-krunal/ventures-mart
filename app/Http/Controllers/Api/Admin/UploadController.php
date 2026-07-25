<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function images(Request $request)
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $folder = 'products/'.Str::uuid()->toString();
        $urls = [];

        foreach ($validated['images'] as $file) {
            $path = $file->store($folder, 'public');
            $urls[] = '/storage/'.$path;
        }

        return response()->json([
            'urls' => $urls,
        ], 201);
    }
}
