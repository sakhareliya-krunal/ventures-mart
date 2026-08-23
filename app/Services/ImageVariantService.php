<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ImageVariantService
{
    public const CARD_WIDTHS = [320, 480, 720];
    public const DETAIL_WIDTHS = [720, 1080, 1440];

    /**
     * @return list<string>
     */
    public function createForPublicUrl(?string $url, array $widths = self::CARD_WIDTHS): array
    {
        $source = $this->absolutePathForPublicUrl($url);

        if ($source === null || ! is_file($source)) {
            return [];
        }

        return $this->createForAbsolutePath($source, $widths);
    }

    /**
     * @return list<string>
     */
    public function createForStoragePath(string $storedPath, array $widths = self::CARD_WIDTHS): array
    {
        return $this->createForAbsolutePath(Storage::disk('public')->path($storedPath), $widths);
    }

    /**
     * @return list<string>
     */
    public function createForAbsolutePath(string $sourcePath, array $widths = self::CARD_WIDTHS): array
    {
        if (! function_exists('imagewebp') || ! is_file($sourcePath)) {
            return [];
        }

        $info = @getimagesize($sourcePath);
        $sourceWidth = (int) ($info[0] ?? 0);
        $sourceHeight = (int) ($info[1] ?? 0);
        $mime = (string) ($info['mime'] ?? '');

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            return [];
        }

        $image = $this->createImageResource($sourcePath, $mime);

        if (! $image) {
            return [];
        }

        $created = [];

        foreach (array_unique(array_map('intval', $widths)) as $width) {
            if ($width < 1) {
                continue;
            }

            $targetWidth = min($width, $sourceWidth);
            $targetHeight = max(1, (int) round($sourceHeight * ($targetWidth / $sourceWidth)));
            $targetPath = $this->variantPath($sourcePath, $width);

            if (is_file($targetPath) && filesize($targetPath) > 0) {
                $created[] = $this->publicUrlForAbsolutePath($targetPath);
                continue;
            }

            if (is_file($targetPath)) {
                @unlink($targetPath);
            }

            File::ensureDirectoryExists(dirname($targetPath));

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

            if (imagewebp($resized, $targetPath, 82)) {
                $created[] = $this->publicUrlForAbsolutePath($targetPath);
            }

            imagedestroy($resized);
        }

        imagedestroy($image);

        return array_values(array_filter($created));
    }

    public function srcsetForPublicUrl(?string $url, array $widths = self::CARD_WIDTHS): ?string
    {
        $source = $this->absolutePathForPublicUrl($url);

        if ($source === null) {
            return null;
        }

        $entries = [];

        foreach (array_unique(array_map('intval', $widths)) as $width) {
            $variant = $this->variantPath($source, $width);

            if (is_file($variant) && filesize($variant) > 0) {
                $entries[] = $this->publicUrlForAbsolutePath($variant).' '.$width.'w';
            }
        }

        return $entries === [] ? null : implode(', ', $entries);
    }

    public function absolutePathForPublicUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '' || preg_match('/^https?:\/\//i', $url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return public_path(ltrim(str_replace('\\', '/', $path), '/'));
    }

    private function createImageResource(string $sourcePath, string $mime)
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
    }

    private function variantPath(string $sourcePath, int $width): string
    {
        $info = pathinfo($sourcePath);

        return ($info['dirname'] ?? '').DIRECTORY_SEPARATOR.($info['filename'] ?? 'image').'-'.$width.'w.webp';
    }

    private function publicUrlForAbsolutePath(string $path): string
    {
        $publicRoot = rtrim(str_replace('\\', '/', public_path()), '/');
        $normalized = str_replace('\\', '/', $path);

        return '/'.ltrim(str_replace($publicRoot, '', $normalized), '/');
    }
}
