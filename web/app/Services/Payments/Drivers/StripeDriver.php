<?php

namespace App\Services\Payments\Drivers;

use App\Contracts\Payable;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * Stripe Checkout for card payments (diaspora givers). Card data never
 * touches FaithFellows servers — PCI scope stays with Stripe.
 */
class StripeDriver extends GatewayDriver
{
    protected const API = 'https://api.stripe.com/v1';

    /** Currencies Stripe treats as zero-decimal. */
    protected const ZERO_DECIMAL = ['UGX', 'RWF', 'JPY', 'KRW', 'XAF', 'XOF', 'BIF', 'CLP', 'DJF', 'GNF', 'KMF', 'MGA', 'PYG', 'VND', 'VUV'];

    protected function configKey(): string
    {
        return 'stripe';
    }

    public static function configured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    public function name(): string
    {
        return 'stripe';
    }

    protected function api()
    {
        return $this->http()->asForm()->withToken($this->config('secret'));
    }

    public function charge(Payable $payable): PaymentResult
    {
        $currency = strtoupper($payable->paymentCurrency());
        $unit = in_array($currency, self::ZERO_DECIMAL, true) ? (int) round($payable->paymentAmount()) : (int) round($payable->paymentAmount() * 100);

        $response = $this->api()->post(self::API.'/checkout/sessions', array_filter([
            'mode' => 'payment',
            'client_reference_id' => $payable->paymentReference(),
            'customer_email' => $payable->paymentEmail(),
            'success_url' => $payable->paymentReturnUrl().'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $payable->paymentReturnUrl().'?cancelled=1',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($currency),
            'line_items[0][price_data][unit_amount]' => $unit,
            'line_items[0][price_data][product_data][name]' => $payable->paymentDescription(),
            'metadata[reference]' => $payable->paymentReference(),
            'payment_intent_data[metadata][reference]' => $payable->paymentReference(),
        ]));
        $data = (array) $response->json();

        return $response->successful() && isset($data['url'])
            ? PaymentResult::redirect($payable->paymentReference(), $data['url'], $data['id'] ?? null)
            : PaymentResult::failed($payable->paymentReference(), $data['error']['message'] ?? __('Card checkout could not be started.'), $data);
    }

    /**
     * @param  string  $reference  Checkout Session id (cs_…)
     */
    public function verify(string $reference): PaymentResult
    {
        $data = (array) $this->api()->get(self::API.'/checkout/sessions/'.$reference)->json();
        $ours = $data['client_reference_id'] ?? null;

        return match (true) {
            ($data['payment_status'] ?? null) === 'paid' => PaymentResult::succeeded($ours, $reference, $data),
            ($data['status'] ?? null) === 'expired' => PaymentResult::failed($ours, __('Checkout expired.'), $data, $reference),
            default => PaymentResult::pending($ours, $reference),
        };
    }

    public function refund(Payable $payable, ?string $providerRef = null): PaymentResult
    {
        $session = (array) $this->api()->get(self::API.'/checkout/sessions/'.$providerRef)->json();
        $data = (array) $this->api()->post(self::API.'/refunds', ['payment_intent' => $session['payment_intent'] ?? null])->json();

        return isset($data['id']) && in_array($data['status'] ?? '', ['succeeded', 'pending'], true)
            ? PaymentResult::refunded($payable->paymentReference(), $data['id'], $data)
            : PaymentResult::failed($payable->paymentReference(), $data['error']['message'] ?? __('Refund failed.'), $data);
    }

    /**
     * Verifies the Stripe-Signature header (HMAC-SHA256, 5-minute tolerance).
     */
    public function webhook(Request $request): ?PaymentResult
    {
        $payload = $request->getContent();
        if (! static::validSignature($payload, (string) $request->header('Stripe-Signature'), (string) $this->config('webhook_secret'))) {
            return null;
        }

        $event = json_decode($payload, true) ?: [];
        $object = $event['data']['object'] ?? [];
        $ours = $object['client_reference_id'] ?? ($object['metadata']['reference'] ?? null);

        return match ($event['type'] ?? null) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => ($object['payment_status'] ?? '') === 'paid'
                ? PaymentResult::succeeded($ours, $object['id'] ?? null, $object) : PaymentResult::pending($ours, $object['id'] ?? null),
            'checkout.session.expired', 'checkout.session.async_payment_failed' => PaymentResult::failed($ours, __('Card payment failed or expired.'), $object, $object['id'] ?? null),
            'charge.dispute.created' => new PaymentResult('disputed', $object['metadata']['reference'] ?? null, null, null, $object['reason'] ?? null, $object),
            default => null,
        };
    }

    public static function validSignature(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }
        $parts = collect(explode(',', $header))->mapWithKeys(function ($p) {
            [$k, $v] = array_pad(explode('=', $p, 2), 2, null);

            return [$k => $v];
        });
        $timestamp = (int) $parts->get('t');
        $signature = (string) $parts->get('v1');

        if (! $timestamp || abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$payload, $secret), $signature);
    }
}
