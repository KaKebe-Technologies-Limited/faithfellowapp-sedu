<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a "Sign in with Google" ID token (a signed JWT) without any Google client library.
 *
 * The app or website gets the token from Google; we check its RS256 signature against Google's
 * published certificates and make sure it was issued for one of our OAuth client IDs, is fresh,
 * and carries a verified email. No client secret is needed for this.
 */
class GoogleIdTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    private const CERTS_CACHE_KEY = 'google.id-token-certs';

    /** @return array<int, string> OAuth client IDs whose tokens we accept. */
    public function clientIds(): array
    {
        return (array) config('services.google.client_ids', []);
    }

    public function enabled(): bool
    {
        return $this->clientIds() !== [];
    }

    /**
     * @return array<string, mixed>|null The token's claims (sub, email, name, picture…), or null if it is not valid.
     */
    public function verify(string $jwt): ?array
    {
        $parts = explode('.', trim($jwt));
        if (count($parts) !== 3 || ! $this->enabled()) {
            return null;
        }

        [$header64, $payload64, $signature64] = $parts;
        $header = json_decode((string) $this->base64UrlDecode($header64), true);
        $claims = json_decode((string) $this->base64UrlDecode($payload64), true);
        $signature = $this->base64UrlDecode($signature64);

        if (! is_array($header) || ! is_array($claims) || $signature === false || ($header['alg'] ?? '') !== 'RS256') {
            return null;
        }

        $kid = (string) ($header['kid'] ?? '');
        $certs = $this->certificates();
        if (! isset($certs[$kid])) {
            $certs = $this->certificates(refresh: true); // Google may have rotated its keys.
        }
        if (! isset($certs[$kid]) || openssl_verify("{$header64}.{$payload64}", $signature, $certs[$kid], OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }

        $now = time();
        $valid = in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            && in_array($claims['aud'] ?? '', $this->clientIds(), true)
            && (int) ($claims['exp'] ?? 0) > $now - 60
            && (int) ($claims['iat'] ?? 0) < $now + 300
            && ! empty($claims['sub'])
            && ! empty($claims['email'])
            && in_array($claims['email_verified'] ?? false, [true, 'true'], true);

        return $valid ? $claims : null;
    }

    /** @return array<string, string> Google's current signing certificates (key id => PEM). */
    protected function certificates(bool $refresh = false): array
    {
        if (! $refresh && is_array($cached = Cache::get(self::CERTS_CACHE_KEY)) && $cached !== []) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get(self::CERTS_URL);
        } catch (\Throwable $e) {
            Log::warning('Google certificates could not be fetched: '.$e->getMessage());

            return [];
        }

        if (! $response->successful() || ! is_array($keys = $response->json()) || $keys === []) {
            Log::warning('Google certificates fetch failed (HTTP '.$response->status().')');

            return [];
        }

        $maxAge = preg_match('/max-age=(\d+)/i', (string) $response->header('Cache-Control'), $m) ? (int) $m[1] : 3600;
        Cache::put(self::CERTS_CACHE_KEY, $keys, max(300, min($maxAge, 86400)));

        return $keys;
    }

    protected function base64UrlDecode(string $value): string|false
    {
        $value = strtr($value, '-_', '+/');
        if ($pad = strlen($value) % 4) {
            $value .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($value, true);
    }
}
