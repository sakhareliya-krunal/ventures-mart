<?php

namespace App\Services;

use App\Jobs\SendMetaConversionEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MetaConversionsService
{
    public const BROWSER_EVENTS = [
        'PageView',
        'ViewContent',
        'AddToCart',
        'InitiateCheckout',
        'Search',
    ];

    public function enabled(): bool
    {
        return filled(config('services.meta.pixel_id'))
            && filled(config('services.meta.access_token'));
    }

    public function queue(
        string $eventName,
        string $eventId,
        array $customData,
        Request $request,
        ?User $user = null,
        ?Order $order = null,
    ): void {
        if (! $this->enabled() || $eventId === '') {
            return;
        }

        SendMetaConversionEvent::dispatch(
            $eventName,
            $eventId,
            $customData,
            $this->eventContext($request, $user, $order),
        );
    }

    public function trackPurchase(Order $order, Request $request): void
    {
        $completed = $order->payment_method === 'cod' || $order->payment_status === 'paid';

        if (! $completed) {
            return;
        }

        if (filled($order->meta_purchase_event_id)) {
            return;
        }

        $eventId = (string) Str::uuid();
        $order->forceFill(['meta_purchase_event_id' => $eventId])->save();

        $this->queue(
            'Purchase',
            $eventId,
            $this->purchaseCustomData($order->loadMissing('items')),
            $request,
            $request->user(),
            $order,
        );
    }

    /**
     * @param  array<string, mixed>  $customData
     * @param  array<string, mixed>  $context
     */
    public function send(string $eventName, string $eventId, array $customData, array $context): void
    {
        if (! $this->enabled()) {
            return;
        }

        $pixelId = (string) config('services.meta.pixel_id');
        $event = array_filter([
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $eventId,
            'event_source_url' => $context['event_source_url'] ?? null,
            'action_source' => 'website',
            'user_data' => $this->userData($context),
            'custom_data' => $customData === [] ? null : $customData,
        ], fn ($value) => $value !== null && $value !== []);

        $payload = array_filter([
            'data' => [$event],
            'access_token' => config('services.meta.access_token'),
            'test_event_code' => config('services.meta.test_event_code') ?: null,
        ]);

        try {
            $response = Http::timeout(8)
                ->asJson()
                ->post("https://graph.facebook.com/v21.0/{$pixelId}/events", $payload);

            if ($response->failed()) {
                Log::warning('Meta CAPI request failed.', [
                    'status' => $response->status(),
                    'event_name' => $eventName,
                    'event_id' => $eventId,
                    'body' => $response->json(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Meta CAPI request errored.', [
                'event_name' => $eventName,
                'event_id' => $eventId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function customDataForBrowserEvent(string $eventName, array $input, Request $request): array
    {
        return match ($eventName) {
            'ViewContent', 'AddToCart' => $this->productCustomData($this->productFromInput($input), $eventName === 'AddToCart' ? $this->quantityFromInput($input) : 1),
            'InitiateCheckout' => $this->cartCustomData($request),
            'Search' => array_filter([
                'search_string' => trim((string) ($input['search_string'] ?? '')),
                'content_type' => 'product',
            ]),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function productCustomData(?Product $product, int $quantity = 1): array
    {
        if (! $product) {
            return [];
        }

        $quantity = max(1, $quantity);
        $value = round((float) $product->price * $quantity, 2);

        return [
            'content_ids' => [(string) $product->id],
            'content_name' => $product->name,
            'content_type' => 'product',
            'content_category' => $product->category?->name,
            'currency' => 'INR',
            'value' => $value,
            'contents' => [[
                'id' => (string) $product->id,
                'quantity' => $quantity,
                'item_price' => (float) $product->price,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cartCustomData(Request $request): array
    {
        $payload = app(CartService::class)->payload($request);
        /** @var \Illuminate\Support\Collection<int, \App\Models\CartItem> $items */
        $items = $payload['items'];

        $contents = [];
        $ids = [];
        $names = [];

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }
            $ids[] = (string) $product->id;
            $names[] = $product->name;
            $contents[] = [
                'id' => (string) $product->id,
                'quantity' => (int) $item->quantity,
                'item_price' => (float) $product->price,
            ];
        }

        return array_filter([
            'content_ids' => $ids,
            'content_name' => implode(', ', $names),
            'content_type' => 'product',
            'currency' => 'INR',
            'value' => (float) ($payload['totals']['total'] ?? 0),
            'contents' => $contents,
            'num_items' => (int) ($payload['quantity_count'] ?? 0),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function purchaseCustomData(Order $order): array
    {
        $contents = [];
        $ids = [];
        $names = [];

        foreach ($order->items as $item) {
            $id = (string) ($item->product_id ?: $item->product_sku);
            $ids[] = $id;
            $names[] = $item->product_name;
            $contents[] = [
                'id' => $id,
                'quantity' => (int) $item->quantity,
                'item_price' => (float) $item->unit_price,
            ];
        }

        return [
            'content_ids' => $ids,
            'content_name' => implode(', ', array_filter($names)),
            'content_type' => 'product',
            'currency' => 'INR',
            'value' => (float) $order->total,
            'contents' => $contents,
            'num_items' => (int) $order->items->sum('quantity'),
            'order_id' => $order->number,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function productFromInput(array $input): ?Product
    {
        $ids = $input['content_ids'] ?? [];
        $raw = is_array($ids) ? ($ids[0] ?? null) : $ids;
        $raw = $raw ?? ($input['product_id'] ?? null);
        $id = (int) $raw;

        if ($id <= 0) {
            return null;
        }

        return Product::query()->with('category')->find($id);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function quantityFromInput(array $input): int
    {
        $contents = $input['contents'] ?? [];
        if (is_array($contents) && isset($contents[0]['quantity'])) {
            return max(1, (int) $contents[0]['quantity']);
        }

        return max(1, (int) ($input['quantity'] ?? 1));
    }

    /**
     * @return array<string, mixed>
     */
    private function eventContext(Request $request, ?User $user, ?Order $order): array
    {
        $email = $order?->email ?: $user?->email;
        $phone = $order?->phone;

        return array_filter([
            'event_source_url' => $request->input('event_source_url') ?: $request->headers->get('referer') ?: url()->current(),
            'client_ip_address' => $request->ip(),
            'client_user_agent' => substr((string) $request->userAgent(), 0, 512) ?: null,
            'fbp' => $request->cookie('_fbp') ?: $request->input('fbp'),
            'fbc' => $request->cookie('_fbc') ?: $request->input('fbc'),
            'em' => $this->hash($email),
            'ph' => $this->hashPhone($phone),
            'fn' => $this->hash($order?->full_name ?: $user?->name),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function userData(array $context): array
    {
        return array_filter([
            'client_ip_address' => $context['client_ip_address'] ?? null,
            'client_user_agent' => $context['client_user_agent'] ?? null,
            'fbp' => $context['fbp'] ?? null,
            'fbc' => $context['fbc'] ?? null,
            'em' => isset($context['em']) ? [$context['em']] : null,
            'ph' => isset($context['ph']) ? [$context['ph']] : null,
            'fn' => isset($context['fn']) ? [$context['fn']] : null,
        ]);
    }

    private function hash(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized === '' ? null : hash('sha256', $normalized);
    }

    private function hashPhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';

        return $digits === '' ? null : hash('sha256', $digits);
    }
}
