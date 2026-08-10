<?php

namespace App\Services;

use App\Exceptions\ShiprocketException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ShiprocketService
{
    private const TOKEN_TTL_SECONDS = 60 * 60 * 24 * 9;

    public function enabled(): bool
    {
        return (bool) config('services.shiprocket.enabled');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pickupLocations(): array
    {
        $response = $this->request('get', '/settings/company/pickup');
        $locations = data_get($response, 'data.shipping_address', []);

        return is_array($locations) ? array_values($locations) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvePickupLocation(): array
    {
        $locations = array_values(array_filter(
            $this->pickupLocations(),
            fn (array $location) => (int) ($location['status'] ?? 0) === 2
        ));
        $configured = trim((string) config('services.shiprocket.pickup_location'));

        if ($configured !== '') {
            $match = collect($locations)->first(
                fn (array $location) => strcasecmp(
                    (string) ($location['pickup_location'] ?? ''),
                    $configured
                ) === 0
            );

            if (! $match) {
                throw new ShiprocketException('The configured Shiprocket pickup location is not active or was not found.');
            }

            return $match;
        }

        $primary = array_values(array_filter(
            $locations,
            fn (array $location) => (int) ($location['is_primary_location'] ?? 0) === 1
        ));

        if (count($primary) !== 1) {
            throw new ShiprocketException(
                'Shiprocket must have exactly one active primary pickup location, or SHIPROCKET_PICKUP_LOCATION must be set.'
            );
        }

        return $primary[0];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceability(array $parameters): array
    {
        return $this->request('get', '/courier/serviceability/', $parameters);
    }

    /**
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        return $this->request('post', '/orders/create/adhoc', $payload);
    }

    /**
     * Recover an existing Shiprocket order by channel order id (local Order.number).
     *
     * @return array{order_id: int, shipment_id: int, awb_code: ?string, courier_name: ?string, courier_company_id: ?int}|null
     */
    public function findOrderByChannelOrderId(string $channelOrderId): ?array
    {
        $channelOrderId = trim($channelOrderId);
        if ($channelOrderId === '') {
            return null;
        }

        $response = $this->request('get', '/orders', [
            'filter_by' => 'channel_order_id',
            'filter' => $channelOrderId,
            'per_page' => 10,
        ]);

        $orders = data_get($response, 'data', []);
        if (! is_array($orders) || $orders === []) {
            $response = $this->request('get', '/orders', [
                'search' => $channelOrderId,
                'per_page' => 10,
            ]);
            $orders = data_get($response, 'data', []);
        }

        if (! is_array($orders)) {
            return null;
        }

        $match = collect($orders)->first(function (mixed $order) use ($channelOrderId): bool {
            if (! is_array($order)) {
                return false;
            }

            $remoteChannelId = (string) (
                $order['channel_order_id']
                ?? $order['order_id']
                ?? ''
            );

            return strcasecmp($remoteChannelId, $channelOrderId) === 0;
        });

        if (! is_array($match)) {
            return null;
        }

        $shiprocketOrderId = (int) ($match['id'] ?? $match['order_id'] ?? 0);
        $shipments = data_get($match, 'shipments', []);
        $firstShipment = is_array($shipments) ? ($shipments[0] ?? null) : null;
        $shipmentId = (int) (
            data_get($firstShipment, 'id')
            ?? data_get($match, 'shipment_id')
            ?? 0
        );

        if ($shiprocketOrderId < 1 || $shipmentId < 1) {
            return null;
        }

        return [
            'order_id' => $shiprocketOrderId,
            'shipment_id' => $shipmentId,
            'awb_code' => filled(data_get($firstShipment, 'awb'))
                ? (string) data_get($firstShipment, 'awb')
                : (filled(data_get($firstShipment, 'awb_code'))
                    ? (string) data_get($firstShipment, 'awb_code')
                    : null),
            'courier_name' => data_get($firstShipment, 'courier')
                ?? data_get($firstShipment, 'courier_name'),
            'courier_company_id' => is_numeric(data_get($firstShipment, 'courier_company_id'))
                ? (int) data_get($firstShipment, 'courier_company_id')
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assignAwb(int $shipmentId, ?int $courierId = null): array
    {
        return $this->request('post', '/courier/assign/awb', array_filter([
            'shipment_id' => $shipmentId,
            'courier_id' => $courierId,
        ], fn ($value) => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function schedulePickup(int $shipmentId): array
    {
        return $this->request('post', '/courier/generate/pickup', [
            'shipment_id' => [$shipmentId],
        ]);
    }

    public function isAlreadyGeneratedPickupError(Throwable|string $error): bool
    {
        $message = strtolower(trim($error instanceof Throwable ? $error->getMessage() : $error));
        if ($message === '') {
            return false;
        }

        $needles = [
            'already generated',
            'already scheduled',
            'pickup already',
            'already been generated',
            'pickup has already',
            'pickup is already',
            'manifest already',
        ];

        foreach ($needles as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function cancelOrder(int $shiprocketOrderId): void
    {
        $this->request('post', '/orders/cancel', ['ids' => [$shiprocketOrderId]]);
    }

    /**
     * @return array<string, mixed>
     */
    public function trackByAwb(string $awb): array
    {
        return $this->request('get', '/courier/track/awb/'.$awb);
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $data = [], bool $retryAuth = true): array
    {
        $this->assertConfigured();

        try {
            $request = $this->client()->withToken($this->token());
            $response = strtolower($method) === 'get'
                ? $request->get($path, $data)
                : $request->{$method}($path, $data);
        } catch (ConnectionException $exception) {
            throw new ShiprocketException('Unable to connect to Shiprocket. Please retry later.');
        }

        if ($response->status() === 401 && $retryAuth) {
            Cache::forget($this->tokenCacheKey());

            return $this->request($method, $path, $data, false);
        }

        return $this->decode($response);
    }

    private function token(): string
    {
        return Cache::remember($this->tokenCacheKey(), self::TOKEN_TTL_SECONDS, function () {
            try {
                $response = $this->client()->post('/auth/login', [
                    'email' => config('services.shiprocket.email'),
                    'password' => config('services.shiprocket.password'),
                ]);
            } catch (ConnectionException $exception) {
                throw new ShiprocketException('Unable to authenticate with Shiprocket. Please retry later.');
            }

            $data = $this->decode($response);
            $token = trim((string) ($data['token'] ?? ''));

            if ($token === '') {
                throw new ShiprocketException('Shiprocket authentication did not return a token.');
            }

            return $token;
        });
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.shiprocket.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(max(5, (int) config('services.shiprocket.timeout', 20)));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $data = $response->json();

        if (! $response->successful()) {
            $message = is_array($data)
                ? (string) ($data['message'] ?? data_get($data, 'errors.0', 'Shiprocket rejected the request.'))
                : 'Shiprocket rejected the request.';

            if (is_array($data)) {
                $body = mb_substr(json_encode($data, JSON_UNESCAPED_UNICODE) ?: '', 0, 3500);
                if ($body !== '' && ! str_contains(strtolower($message), strtolower(mb_substr($body, 0, 80)))) {
                    $message = trim($message.' | '.$body);
                }
            }

            throw new ShiprocketException(mb_substr($message, 0, 4000), $response->status());
        }

        return is_array($data) ? $data : [];
    }

    private function assertConfigured(): void
    {
        if (! $this->enabled()) {
            throw new ShiprocketException('Shiprocket integration is disabled.');
        }

        if (
            blank(config('services.shiprocket.email'))
            || blank(config('services.shiprocket.password'))
        ) {
            throw new ShiprocketException('Shiprocket API credentials are not configured.');
        }
    }

    private function tokenCacheKey(): string
    {
        return 'shiprocket.token.'.sha1((string) config('services.shiprocket.email'));
    }
}
