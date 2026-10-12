<?php

namespace App\Services\Payments\Drivers;

use App\Contracts\PaymentDriver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Shared helpers for HTTP gateways: token caching, phone formatting, timeouts.
 *
 * Security note: mobile-money callbacks (MTN, Airtel, Iotec) are never
 * trusted on their own — the webhook only identifies the transaction, and
 * DonationService re-checks its status with the gateway API (verify()).
 */
abstract class GatewayDriver implements PaymentDriver
{
    abstract protected function configKey(): string;

    public static function configured(): bool
    {
        return false;
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config('services.'.$this->configKey().'.'.$key, $default);
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(30)->connectTimeout(10)->retry(2, 500, throw: false)->acceptJson();
    }

    /**
     * Cache an OAuth access token until shortly before it expires.
     *
     * @param  callable(): array{access_token: string, expires_in?: int}  $fetch
     */
    protected function cachedToken(callable $fetch): string
    {
        $key = 'payment_token:'.$this->configKey();

        if ($token = Cache::get($key)) {
            return $token;
        }

        $data = $fetch();
        $token = $data['access_token'] ?? throw new \RuntimeException('Could not authenticate with '.$this->configKey());
        Cache::put($key, $token, now()->addSeconds(max(60, (int) ($data['expires_in'] ?? 3600) - 120)));

        return $token;
    }

    /**
     * Normalise Ugandan numbers to 256XXXXXXXXX (MSISDN without +).
     */
    public static function msisdn(?string $phone, string $countryCode = '256'): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return match (true) {
            str_starts_with($digits, $countryCode) => $digits,
            str_starts_with($digits, '0') => $countryCode.substr($digits, 1),
            strlen($digits) === 9 => $countryCode.$digits,
            default => $digits,
        };
    }

    /** Local 9-digit form (7XXXXXXXX) used by Airtel. */
    public static function localNumber(?string $phone): string
    {
        return substr(static::msisdn($phone), -9);
    }

    /** Which network a Ugandan number belongs to (for auto-selecting MTN vs Airtel). */
    public static function network(?string $phone): ?string
    {
        $prefix = substr(static::localNumber($phone), 0, 2);

        return match (true) {
            in_array($prefix, ['77', '78', '76', '39'], true) => 'mtn_momo',
            in_array($prefix, ['70', '75', '74', '20'], true) => 'airtel_money',
            default => null,
        };
    }
}
