<?php

namespace App\Services;

use App\Exceptions\ShiprocketException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

            throw new ShiprocketException($message, $response->status());
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
