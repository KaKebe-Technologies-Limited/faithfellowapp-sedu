<?php

namespace App\Services\Payments\Drivers;

use App\Contracts\Payable;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * Airtel Money — Collection API (USSD push).
 * Docs: https://developers.airtel.africa/documentation
 */
class AirtelMoneyDriver extends GatewayDriver
{
    protected function configKey(): string
    {
        return 'airtel';
    }

    public static function configured(): bool
    {
        return filled(config('services.airtel.client_id')) && filled(config('services.airtel.client_secret'));
    }

    public function name(): string
    {
        return 'airtel_money';
    }

    protected function token(): string
    {
        return $this->cachedToken(fn () => $this->http()->post($this->config('base_url').'/auth/oauth2/token', [
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'grant_type' => 'client_credentials',
        ])->json());
    }

    protected function client()
    {
        return $this->http()->withToken($this->token())->withHeaders([
            'X-Country' => $this->config('country', 'UG'),
            'X-Currency' => $this->config('currency', 'UGX'),
        ]);
    }

    public function charge(Payable $payable): PaymentResult
    {
        $response = $this->client()->post($this->config('base_url').'/merchant/v1/payments/', [
            'reference' => substr($payable->paymentDescription(), 0, 60),
            'subscriber' => ['country' => $this->config('country', 'UG'), 'currency' => $this->config('currency', 'UGX'), 'msisdn' => static::localNumber($payable->paymentPhone())],
            'transaction' => [
                'amount' => (int) round($payable->paymentAmount()),
                'country' => $this->config('country', 'UG'),
                'currency' => $this->config('currency', 'UGX'),
                'id' => $payable->paymentReference(),
            ],
        ]);

        return $response->json('status.success')
            ? PaymentResult::pending($payable->paymentReference(), $payable->paymentReference(), __('Approve the Airtel Money prompt on your phone and enter your PIN.'), (array) $response->json())
            : PaymentResult::failed($payable->paymentReference(), $response->json('status.message') ?? __('Airtel Money request failed.'), (array) $response->json());
    }

    public function verify(string $reference): PaymentResult
    {
        $data = (array) $this->client()->get($this->config('base_url').'/standard/v1/payments/'.$reference)->json();
        $tx = $data['data']['transaction'] ?? [];

        return match ($tx['status'] ?? null) {
            'TS' => PaymentResult::succeeded($reference, $tx['airtel_money_id'] ?? $reference, $data),
            'TF', 'TE' => PaymentResult::failed($reference, $tx['message'] ?? __('Payment was not completed.'), $data),
            default => PaymentResult::pending($reference, $reference),
        };
    }

    public function refund(Payable $payable, ?string $providerRef = null): PaymentResult
    {
        $data = (array) $this->client()->post($this->config('base_url').'/standard/v1/payments/refund', [
            'transaction' => ['airtel_money_id' => $providerRef],
        ])->json();

        return ($data['status']['success'] ?? false)
            ? PaymentResult::refunded($payable->paymentReference(), $providerRef, $data)
            : PaymentResult::failed($payable->paymentReference(), $data['status']['message'] ?? __('Refund failed.'), $data);
    }

    public function webhook(Request $request): ?PaymentResult
    {
        // Optional HMAC check when Airtel callback authentication is enabled.
        if ($secret = $this->config('webhook_secret')) {
            $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));
            if (! hash_equals($expected, (string) $request->header('X-Signature', $request->input('hash', '')))) {
                return null;
            }
        }

        $ours = $request->input('transaction.id');

        return $ours ? new PaymentResult('pending', $ours, null, null, 'callback', $request->all()) : null;
    }
}
