<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private readonly ProductQueryService $products)
    {
    }

    public function ownerKey(Request $request): array
    {
        if ($request->user()) {
            return ['user_id' => $request->user()->id, 'session_id' => null];
        }

        if (! $request->session()->has('cart_session_id')) {
            $request->session()->put('cart_session_id', $request->session()->getId());
        }

        return ['user_id' => null, 'session_id' => $request->session()->get('cart_session_id')];
    }

    public function items(Request $request): Collection
    {
        $owner = $this->ownerKey($request);

        return CartItem::query()
            ->with('product')
            ->when($owner['user_id'], fn ($q) => $q->where('user_id', $owner['user_id']))
            ->when(! $owner['user_id'], fn ($q) => $q->where('session_id', $owner['session_id']))
            ->get();
    }

    public function payload(Request $request, ?string $destinationState = null): array
    {
        $items = $this->items($request);
        $lines = $items->map(fn (CartItem $item) => [
            'price' => $item->product?->price ?? 0,
            'quantity' => $item->quantity,
        ]);

        $state = $destinationState ?? $request->query('state') ?? $request->input('state');

        return [
            'items' => $items,
            'item_count' => $items->count(),
            'quantity_count' => $items->sum('quantity'),
            'totals' => $this->products->calculateTotals($lines, is_string($state) ? $state : null),
        ];
    }

    public function add(Request $request, Product $product, int $quantity = 1): array
    {
        $quantity = max(1, min(99, $quantity));

        if (! $product->is_active) {
            throw ValidationException::withMessages([
                'product' => 'This product is unavailable.',
            ]);
        }

        if ($product->stock < 1) {
            throw ValidationException::withMessages([
                'product' => 'This product is out of stock.',
            ]);
        }

        $owner = $this->ownerKey($request);

        $item = CartItem::query()
            ->when($owner['user_id'], fn ($q) => $q->where('user_id', $owner['user_id']))
            ->when(! $owner['user_id'], fn ($q) => $q->where('session_id', $owner['session_id']))
            ->where('product_id', $product->id)
            ->first();

        $nextQuantity = $item
            ? min(99, $item->quantity + $quantity)
            : $quantity;

        if ($nextQuantity > $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$product->stock} left in stock.",
            ]);
        }

        if ($item) {
            $item->update(['quantity' => $nextQuantity]);
        } else {
            CartItem::query()->create([
                ...$owner,
                'product_id' => $product->id,
                'quantity' => $nextQuantity,
            ]);
        }

        return $this->payload($request);
    }

    public function update(Request $request, Product $product, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->remove($request, $product);
        }

        if ($quantity > $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => $product->stock < 1
                    ? 'This product is out of stock.'
                    : "Only {$product->stock} left in stock.",
            ]);
        }

        $owner = $this->ownerKey($request);
        $item = CartItem::query()
            ->when($owner['user_id'], fn ($q) => $q->where('user_id', $owner['user_id']))
            ->when(! $owner['user_id'], fn ($q) => $q->where('session_id', $owner['session_id']))
            ->where('product_id', $product->id)
            ->first();

        if (! $item) {
            throw ValidationException::withMessages(['product' => 'Item not found in cart.']);
        }

        $item->update(['quantity' => min(99, $quantity)]);

        return $this->payload($request);
    }

    public function remove(Request $request, Product $product): array
    {
        $owner = $this->ownerKey($request);

        CartItem::query()
            ->when($owner['user_id'], fn ($q) => $q->where('user_id', $owner['user_id']))
            ->when(! $owner['user_id'], fn ($q) => $q->where('session_id', $owner['session_id']))
            ->where('product_id', $product->id)
            ->delete();

        return $this->payload($request);
    }

    public function clear(Request $request): void
    {
        $owner = $this->ownerKey($request);

        CartItem::query()
            ->when($owner['user_id'], fn ($q) => $q->where('user_id', $owner['user_id']))
            ->when(! $owner['user_id'], fn ($q) => $q->where('session_id', $owner['session_id']))
            ->delete();
    }

    public function mergeGuestIntoUser(Request $request, User $user): void
    {
        $sessionId = $request->session()->get('cart_session_id') ?? $request->session()->getId();

        $guestItems = CartItem::query()->where('session_id', $sessionId)->whereNull('user_id')->get();

        DB::transaction(function () use ($guestItems, $user) {
            foreach ($guestItems as $guestItem) {
                $existing = CartItem::query()
                    ->where('user_id', $user->id)
                    ->where('product_id', $guestItem->product_id)
                    ->first();

                if ($existing) {
                    $existing->update(['quantity' => min(99, $existing->quantity + $guestItem->quantity)]);
                    $guestItem->delete();
                } else {
                    $guestItem->update(['user_id' => $user->id, 'session_id' => null]);
                }
            }
        });
    }
}
