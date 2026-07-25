<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'external_id',
        'slug',
        'name',
        'sku',
        'category_id',
        'price',
        'compare_at_price',
        'rating',
        'reviews',
        'image',
        'hover_image',
        'badge',
        'tags',
        'description',
        'details',
        'stock',
        'variant_group_id',
        'color_name',
        'color_hex',
        'gallery',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'compare_at_price' => 'float',
            'rating' => 'float',
            'tags' => 'array',
            'details' => 'array',
            'gallery' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function refreshReviewAggregates(): void
    {
        $stats = $this->productReviews()
            ->selectRaw('COUNT(*) as review_count, COALESCE(AVG(rating), 0) as avg_rating')
            ->first();

        $this->forceFill([
            'reviews' => (int) ($stats->review_count ?? 0),
            'rating' => round((float) ($stats->avg_rating ?? 0), 1),
        ])->save();
    }

    public function siblingVariants(): Collection
    {
        if (! $this->variant_group_id) {
            return collect([$this]);
        }

        return static::query()
            ->where('variant_group_id', $this->variant_group_id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Build gallery URLs from stored gallery, or by scanning local public folders.
     *
     * @return list<string>
     */
    public function localGallery(): array
    {
        $primary = $this->normalizePublicPath($this->image);
        $storedGallery = collect($this->gallery ?? [])
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->map(fn (string $path) => $this->normalizePublicPath($path))
            ->values()
            ->all();

        if ($storedGallery !== []) {
            $images = $storedGallery;

            if ($primary !== '') {
                $images = array_values(array_filter($images, fn (string $path) => $path !== $primary));
                array_unshift($images, $primary);
            }

            return array_values(array_unique($images));
        }

        $images = [];
        $hover = $this->normalizePublicPath($this->hover_image);

        if ($primary !== '' && str_contains($primary, '/hero-showcase/')) {
            $images[] = $primary;
        }

        $folderPath = $this->resolveLocalImageDirectory($primary, $hover);

        if ($folderPath !== null && is_dir($folderPath)) {
            $files = collect(scandir($folderPath) ?: [])
                ->reject(fn (string $file) => in_array($file, ['.', '..'], true))
                ->filter(fn (string $file) => (bool) preg_match('/\.(jpe?g|png|webp)$/i', $file))
                ->sort(fn (string $a, string $b) => strnatcasecmp($a, $b))
                ->values();

            $publicBase = $this->publicUrlForDirectory($folderPath);

            foreach ($files as $file) {
                $images[] = $publicBase.'/'.$file;
            }
        }

        if ($hover !== '' && ! in_array($hover, $images, true) && ! str_contains($hover, '/hero-showcase/')) {
            $images[] = $hover;
        }

        $images = array_values(array_unique($images));

        if ($primary !== '') {
            $images = array_values(array_filter($images, fn (string $path) => $path !== $primary));
            array_unshift($images, $primary);
        }

        return $images;
    }

    private function resolveLocalImageDirectory(string $primary, string $hover): ?string
    {
        foreach ([$hover, $primary] as $path) {
            if ($path === '' || str_contains($path, '/hero-showcase/')) {
                continue;
            }

            $absolute = public_path(ltrim($path, '/'));
            $directory = is_file($absolute) ? dirname($absolute) : $absolute;

            if (is_dir($directory)) {
                return $directory;
            }
        }

        return null;
    }

    private function publicUrlForDirectory(string $absoluteDirectory): string
    {
        $publicRoot = rtrim(str_replace('\\', '/', public_path()), '/');
        $directory = rtrim(str_replace('\\', '/', $absoluteDirectory), '/');
        $relative = ltrim(Str::after($directory, $publicRoot), '/');

        return '/'.$relative;
    }

    private function normalizePublicPath(?string $path): string
    {
        if (! is_string($path) || $path === '') {
            return '';
        }

        return '/'.ltrim(str_replace('\\', '/', $path), '/');
    }
}
