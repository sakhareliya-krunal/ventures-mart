<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class GoogleIdTokenVerifier
{
    /**
     * @return array{sub: string, email: string, email_verified: bool, name: ?string, picture: ?string}
     */
    public function verify(string $credential): array
    {
        $clientId = (string) config('services.google.client_id');

        if ($clientId === '') {
            throw new RuntimeException('Google sign-in is not configured.');
        }

        try {
            $keys = $this->googleCerts();
            $payload = JWT::decode($credential, JWK::parseKeySet($keys));
        } catch (UnexpectedValueException $exception) {
            throw new RuntimeException('Invalid Google credential.', 0, $exception);
        }

        $aud = $payload->aud ?? null;
        $iss = $payload->iss ?? null;
        $email = isset($payload->email) ? strtolower((string) $payload->email) : '';
        $sub = isset($payload->sub) ? (string) $payload->sub : '';

        $audiences = is_array($aud) ? $aud : [$aud];
        if (! in_array($clientId, $audiences, true)) {
            throw new RuntimeException('Invalid Google credential audience.');
        }

        if (! in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new RuntimeException('Invalid Google credential issuer.');
        }

        if ($sub === '' || $email === '') {
            throw new RuntimeException('Google account is missing required profile details.');
        }

        $verified = filter_var($payload->email_verified ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $verified) {
            throw new RuntimeException('Google email address is not verified.');
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'email_verified' => true,
            'name' => isset($payload->name) ? (string) $payload->name : null,
            'picture' => isset($payload->picture) ? (string) $payload->picture : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function googleCerts(): array
    {
        return Cache::remember('google_oauth_jwks', 3600, function () {
            try {
                $response = Http::timeout(10)->get('https://www.googleapis.com/oauth2/v3/certs');
            } catch (ConnectionException|RequestException|Throwable $exception) {
                throw new RuntimeException('Unable to reach Google sign-in. Please try again.', 0, $exception);
            }

            if (! $response->successful()) {
                throw new RuntimeException('Unable to reach Google sign-in. Please try again.');
            }

            /** @var array<string, mixed> $json */
            $json = $response->json();

            return $json;
        });
    }
}
