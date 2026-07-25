<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    public function ownerKey(Request $request): array
    {
        if ($request->user()) {
            return ['user_id' => $request->user()->id, 'session_id' => null];
        }

        if (! $request->session()->has('wishlist_session_id')) {
            $request->session()->put('wishlist_session_id', $request->session()->getId());
        }

        return ['user_id' => null, 'session_id' => $request->session()->get('wishlist_session_id')];
    }

    public function items(Request $request): Collection
    {
        $owner = $this->ownerKey($request);

        return WishlistItem::query()
            ->with('product.category')
            ->when($owner['user_id'], fn ($q) => $q->where('user_id', $owner['user_id']))
            ->when(! $owner['user_id'], fn ($q) => $q->where('session_id', $owner['session_id']))
            ->get();
    }

    public function productIds(Request $request): array
    {
        $owner = $this->ownerKey($request);

        return WishlistItem::query()
            ->when($owner['user_id'], fn ($q) => $q->where('user_id', $owner['user_id']))
            ->when(! $owner['user_id'], fn ($q) => $q->where('session_id', $owner['session_id']))
            ->pluck('product_id')
            ->all();
    }

    public function toggle(Request $request, Product $product): array
    {
        $owner = $this->ownerKey($request);

        $existing = WishlistItem::query()
            ->when($owner['user_id'], fn ($q) => $q->where('user_id', $owner['user_id']))
            ->when(! $owner['user_id'], fn ($q) => $q->where('session_id', $owner['session_id']))
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $wished = false;
        } else {
            WishlistItem::query()->create([
                ...$owner,
                'product_id' => $product->id,
            ]);
            $wished = true;
        }

        $ids = $this->productIds($request);

        return [
            'wished' => $wished,
            'count' => count($ids),
            'product_ids' => $ids,
        ];
    }

    public function remove(Request $request, Product $product): array
    {
        $owner = $this->ownerKey($request);

        WishlistItem::query()
            ->when($owner['user_id'], fn ($q) => $q->where('user_id', $owner['user_id']))
            ->when(! $owner['user_id'], fn ($q) => $q->where('session_id', $owner['session_id']))
            ->where('product_id', $product->id)
            ->delete();

        $ids = $this->productIds($request);

        return [
            'count' => count($ids),
            'product_ids' => $ids,
        ];
    }

    public function mergeGuestIntoUser(Request $request, User $user): void
    {
        $sessionId = $request->session()->get('wishlist_session_id') ?? $request->session()->getId();
        $guestItems = WishlistItem::query()->where('session_id', $sessionId)->whereNull('user_id')->get();

        DB::transaction(function () use ($guestItems, $user) {
            foreach ($guestItems as $guestItem) {
                $exists = WishlistItem::query()
                    ->where('user_id', $user->id)
                    ->where('product_id', $guestItem->product_id)
                    ->exists();

                if ($exists) {
                    $guestItem->delete();
                } else {
                    $guestItem->update(['user_id' => $user->id, 'session_id' => null]);
                }
            }
        });
    }
}
