<?php

namespace App\Services\Payments;

/**
 * Normalised outcome of a gateway call.
 *
 *  status:
 *   - pending    — prompt sent to the phone / awaiting confirmation
 *   - redirect   — send the giver to $redirectUrl (hosted card checkout)
 *   - succeeded  — money received
 *   - failed     — declined, cancelled or timed out
 *   - refunded   — refund completed
 */
final class PaymentResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $reference = null,
        public readonly ?string $providerRef = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $message = null,
        public readonly array $payload = [],
    ) {}

    public static function pending(?string $reference, ?string $providerRef = null, ?string $message = null, array $payload = []): self
    {
        return new self('pending', $reference, $providerRef, null, $message, $payload);
    }

    public static function redirect(?string $reference, string $url, ?string $providerRef = null, array $payload = []): self
    {
        return new self('redirect', $reference, $providerRef, $url, null, $payload);
    }

    public static function succeeded(?string $reference, ?string $providerRef, array $payload = []): self
    {
        return new self('succeeded', $reference, $providerRef, null, null, $payload);
    }

    public static function failed(?string $reference, ?string $message = null, array $payload = [], ?string $providerRef = null): self
    {
        return new self('failed', $reference, $providerRef, null, $message, $payload);
    }

    public static function refunded(?string $reference, ?string $providerRef = null, array $payload = []): self
    {
        return new self('refunded', $reference, $providerRef, null, null, $payload);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['succeeded', 'failed', 'refunded'], true);
    }
}
