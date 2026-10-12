<?php

namespace App\Services\Payments\Drivers;

use App\Contracts\Payable;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * Iotec Pay — aggregates MTN & Airtel mobile money collections in Uganda.
 * Docs: https://pay.iotec.io (Collections API)
 */
class IotecDriver extends GatewayDriver
{
    protected function configKey(): string
    {
        return 'iotec';
    }

    public static function configured(): bool
    {
        return filled(config('services.iotec.client_id')) && filled(config('services.iotec.client_secret')) && filled(config('services.iotec.wallet_id'));
    }

    public function name(): string
    {
        return 'iotec';
    }

    protected function token(): string
    {
        return $this->cachedToken(fn () => $this->http()->asForm()->post($this->config('auth_url'), [
            'client_id' => $this->config('client_id'),
            'client_secret' => $this->config('client_secret'),
            'grant_type' => 'client_credentials',
        ])->json());
    }

    public function charge(Payable $payable): PaymentResult
    {
        $response = $this->http()->withToken($this->token())->post($this->config('base_url').'/collections/collect', [
            'category' => 'MobileMoney',
            'currency' => $payable->paymentCurrency(),
            'walletId' => $this->config('wallet_id'),
            'externalId' => $payable->paymentReference(),
            'payer' => '0'.static::localNumber($payable->paymentPhone()),
            'amount' => (int) round($payable->paymentAmount()),
            'payerNote' => substr($payable->paymentDescription(), 0, 60),
            'payeeNote' => $payable->paymentReference(),
        ]);
        $data = (array) $response->json();

        if (! $response->successful() || ($data['status'] ?? '') === 'Failed') {
            return PaymentResult::failed($payable->paymentReference(), $data['statusMessage'] ?? __('Iotec request failed.'), $data);
        }

        return PaymentResult::pending($payable->paymentReference(), $data['id'] ?? null, __('Approve the mobile money prompt on your phone.'), $data);
    }

    /**
     * @param  string  $reference  Iotec transaction id (provider_ref)
     */
    public function verify(string $reference): PaymentResult
    {
        $data = (array) $this->http()->withToken($this->token())->get($this->config('base_url').'/collections/status/'.$reference)->json();
        $ours = $data['externalId'] ?? null;

        return match ($data['status'] ?? null) {
            'Success' => PaymentResult::succeeded($ours, $reference, $data),
            'Failed' => PaymentResult::failed($ours, $data['statusMessage'] ?? __('Payment was not completed.'), $data, $reference),
            default => PaymentResult::pending($ours, $reference),
        };
    }

    public function refund(Payable $payable, ?string $providerRef = null): PaymentResult
    {
        return PaymentResult::failed($payable->paymentReference(), __('Iotec refunds are processed as a disbursement by finance.'));
    }

    public function webhook(Request $request): ?PaymentResult
    {
        if ($secret = $this->config('webhook_secret')) {
            $expected = hash_hmac('sha256', $request->getContent(), $secret);
            if (! hash_equals($expected, (string) $request->header('X-Iotec-Signature'))) {
                return null;
            }
        }

        $ours = $request->input('externalId');

        return $ours ? new PaymentResult('pending', $ours, $request->input('id'), null, 'callback', $request->all()) : null;
    }
}
