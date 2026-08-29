<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageVariantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function __construct(private readonly ImageVariantService $images)
    {
    }

    public function images(Request $request)
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'purpose' => ['sometimes', 'string', 'in:products,banners'],
        ]);

        $purpose = $validated['purpose'] ?? 'products';
        $folder = $purpose.'/'.Str::uuid()->toString();
        $urls = [];

        foreach ($validated['images'] as $file) {
            $path = $file->store($folder, 'public');
            $webp = $this->createWebpDerivative($file->getRealPath(), $path);
            $storedPath = $webp ?: $path;
            $this->images->createForStoragePath(
                $storedPath,
                array_values(array_unique([...ImageVariantService::CARD_WIDTHS, ...ImageVariantService::DETAIL_WIDTHS])),
            );
            $urls[] = '/storage/'.$storedPath;
        }

        return response()->json([
            'urls' => $urls,
        ], 201);
    }

    private function createWebpDerivative(string $sourcePath, string $storedPath): ?string
    {
        if (! function_exists('imagewebp')) {
            return null;
        }

        $info = @getimagesize($sourcePath);
        $mime = $info['mime'] ?? '';
        $image = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => null,
            default => false,
        };

        if ($image === null) {
            return $storedPath;
        }

        if (! $image) {
            return null;
        }

        $webpPath = preg_replace('/\.[^.]+$/', '.webp', $storedPath) ?: $storedPath.'.webp';
        $absolute = Storage::disk('public')->path($webpPath);

        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }

        $created = imagewebp($image, $absolute, 82);
        imagedestroy($image);

        return $created ? $webpPath : null;
    }
}
