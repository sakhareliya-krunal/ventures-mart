<?php

namespace App\Services;

use App\Models\SeoRedirect;

class SeoRedirectService
{
    /**
     * Create/update a 301 from $oldPath to $newPath and rewrite any redirects
     * that previously targeted $oldPath so chains resolve to the latest URL.
     */
    public function redirectSlugChange(string $oldPath, string $newPath): void
    {
        $oldPath = $this->normalize($oldPath);
        $newPath = $this->normalize($newPath);

        if ($oldPath === $newPath) {
            return;
        }

        SeoRedirect::query()->updateOrCreate(
            ['old_path' => $oldPath],
            [
                'target_path' => $newPath,
                'status_code' => 301,
                'is_active' => true,
            ],
        );

        SeoRedirect::query()
            ->where('target_path', $oldPath)
            ->where('old_path', '!=', $newPath)
            ->update(['target_path' => $newPath]);

        // Prevent redirect loops from the new canonical path.
        SeoRedirect::query()
            ->where('old_path', $newPath)
            ->delete();
    }

    private function normalize(string $path): string
    {
        $normalized = '/'.trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }
}
