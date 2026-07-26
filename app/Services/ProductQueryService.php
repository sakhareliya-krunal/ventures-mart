<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductQueryService
{
    public function query(array $filters = []): Collection
    {
        $query = Product::query()->active()->with('category');

        if (! empty($filters['q'])) {
            $term = mb_strtolower(trim($filters['q']));
            $query->where(function (Builder $builder) use ($term) {
                $builder->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(color_name) LIKE ?', ["%{$term}%"])
                    ->orWhereHas('category', fn (Builder $category) => $category->whereRaw('LOWER(slug) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(name) LIKE ?', ["%{$term}%"]));
            });
        }

        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $filters['category']));
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        $sort = $filters['sort'] ?? 'featured';

        $products = match ($sort) {
            'price-asc' => $query->orderBy('price')->get(),
            'price-desc' => $query->orderByDesc('price')->get(),
            'rating' => $query->orderByDesc('rating')->get(),
            'newest' => $query->orderByDesc('id')->get(),
            default => $query->orderByRaw("CASE WHEN badge IS NULL OR badge = '' THEN 1 ELSE 0 END")
                ->orderByDesc('rating')
                ->get(),
        };

        return $this->groupForListing($products);
    }

    public function findBySlug(string $slug): ?Product
    {
        $product = Product::query()->active()->with('category')->where('slug', $slug)->first();

        if ($product) {
            $product->setRelation('colorVariants', $product->siblingVariants());
        }

        return $product;
    }

    public function featured(int $limit = 16): Collection
    {
        $products = Product::query()
            ->active()
            ->with('category')
            ->where(function (Builder $query) {
                $query->where('badge', 'Featured')
                    ->orWhereJsonContains('tags', 'featured');
            })
            ->orderByDesc('rating')
            ->get();

        return $this->groupForListing($products)->take($limit)->values();
    }

    public function sale(int $limit = 8): Collection
    {
        $products = Product::query()
            ->active()
            ->with('category')
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->orderByDesc('rating')
            ->get();

        return $this->groupForListing($products)->take($limit)->values();
    }

    public function related(Product $product, int $limit = 4): Collection
    {
        $products = Product::query()
            ->active()
            ->with('category')
            ->where('category_id', $product->category_id)
            ->when(
                $product->variant_group_id,
                fn (Builder $query) => $query->where(function (Builder $builder) use ($product) {
                    $builder->whereNull('variant_group_id')
                        ->orWhere('variant_group_id', '!=', $product->variant_group_id);
                }),
                fn (Builder $query) => $query->where('id', '!=', $product->id),
            )
            ->orderByDesc('rating')
            ->get();

        return $this->groupForListing($products)
            ->reject(fn (Product $item) => $item->id === $product->id)
            ->take($limit)
            ->values();
    }

    public function calculateTotals(iterable $lines): array
    {
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $price = is_array($line)
                ? (float) ($line['price'] ?? 0)
                : (float) ($line->product->price ?? 0);
            $qty = is_array($line)
                ? (int) ($line['quantity'] ?? 0)
                : (int) $line->quantity;
            $subtotal += $price * $qty;
        }

        $shipping = $subtotal > 0 && $subtotal < 999 ? 49.0 : 0.0;
        $tax = round($subtotal * 0.05, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => round($subtotal + $shipping + $tax, 2),
        ];
    }

    private function groupForListing(Collection $products): Collection
    {
        return $products
            ->groupBy(fn (Product $product) => $product->variant_group_id ?: $product->slug)
            ->map(function (Collection $group) {
                $primary = $group->first();
                $primary->setRelation('colorVariants', $group->values());

                return $primary;
            })
            ->values();
    }
}
