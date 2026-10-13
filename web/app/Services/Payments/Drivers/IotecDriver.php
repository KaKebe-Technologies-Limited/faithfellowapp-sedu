<?php

namespace App\Services\Payments\Drivers;

use App\Contracts\Payable;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * ioTec Pay — one account that collects MTN and Airtel mobile money in Uganda: the giver enters their number and
 * a PIN prompt appears on their phone straight away. Same flow as the Kakebe Tech Camp site:
 * OAuth client credentials → collect → status. Docs: https://iotec.io/api-docs/pay
 *
 * Sandbox (IOTEC_SANDBOX=true, local machines only): the test wallet and the test currency ITX, which only accept
 * ioTec's test numbers such as 0111777771 — no real money moves. Live uses the live wallet and the church's currency.
 */
class IotecDriver extends GatewayDriver
{
    protected function configKey(): string
    {
        return 'iotec';
    }

    /** Sandbox is only honoured on a local machine, so a live server can never end up in test mode by accident. */
    public static function sandbox(): bool
    {
        return (bool) config('services.iotec.sandbox') && app()->environment('local', 'testing');
    }

    public static function walletId(): ?string
    {
        return static::sandbox() ? config('services.iotec.test_wallet_id') : config('services.iotec.wallet_id');
    }

    public static function configured(): bool
    {
        return filled(config('services.iotec.client_id')) && filled(config('services.iotec.client_secret')) && filled(static::walletId());
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
        ])->json() ?? []);
    }

    public function charge(Payable $payable): PaymentResult
    {
        try {
            $response = $this->http()->withToken($this->token())->post($this->config('base_url').'/collections/collect', [
                'category' => 'MobileMoney',
                'currency' => static::sandbox() ? 'ITX' : $payable->paymentCurrency(),
                'walletId' => static::walletId(),
                'externalId' => $payable->paymentReference(),
                'payer' => static::msisdn($payable->paymentPhone()),
                'amount' => (int) round($payable->paymentAmount()),
                'payerNote' => substr($payable->paymentDescription(), 0, 60),
                'payeeNote' => $payable->paymentReference(),
                'transactionChargesCategory' => 'ChargeWallet',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return PaymentResult::failed($payable->paymentReference(), __('We could not reach the payment service. Please try again in a moment.'));
        }
        $data = (array) $response->json();
        $status = strtolower((string) ($data['status'] ?? ''));

        if (! $response->successful() || in_array($status, ['failed', 'cancelled', 'rejected'], true)) {
            return PaymentResult::failed($payable->paymentReference(), $this->errorText($data, $response->status()), $data);
        }
        // ioTec can settle at once (the sandbox's success numbers do).
        if ($status === 'success') {
            return PaymentResult::succeeded($payable->paymentReference(), $data['id'] ?? null, $data);
        }

        return PaymentResult::pending($payable->paymentReference(), $data['id'] ?? null, __('Approve the mobile money prompt on your phone.'), $data);
    }

    /**
     * @param  string  $reference  ioTec transaction id (provider_ref)
     */
    public function verify(string $reference): PaymentResult
    {
        $response = $this->http()->withToken($this->token())->get($this->config('base_url').'/collections/status/'.$reference);
        $data = (array) $response->json();
        $ours = $data['externalId'] ?? null;

        return match (strtolower((string) ($data['status'] ?? ''))) {
            'success' => PaymentResult::succeeded($ours, $reference, $data),
            'failed', 'cancelled', 'rejected' => PaymentResult::failed($ours, $data['statusMessage'] ?? __('Payment was not completed.'), $data, $reference),
            default => PaymentResult::pending($ours, $reference),
        };
    }

    public function refund(Payable $payable, ?string $providerRef = null): PaymentResult
    {
        return PaymentResult::failed($payable->paymentReference(), __('ioTec refunds are processed as a disbursement by finance.'));
    }

    /**
     * ioTec's payment notice. It is only used to find the donation: the amount and result are always re-checked
     * with ioTec's status API. When a secret is set the notice must carry it (header, query string or body) or a
     * matching HMAC signature.
     */
    public function webhook(Request $request): ?PaymentResult
    {
        $ipn = (string) $this->config('ipn_secret');
        $hmac = (string) $this->config('webhook_secret');

        if ($ipn !== '' || $hmac !== '') {
            $presented = (string) ($request->header('X-IPN-Secret') ?: $request->header('X-Iotec-Secret') ?: $request->header('X-Iotec-Signature') ?: $request->query('secret') ?: $request->input('secret') ?: $request->input('ipnSecret'));
            $ok = ($ipn !== '' && hash_equals($ipn, $presented))
                || ($hmac !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $hmac), $presented));
            if (! $ok) {
                return null;
            }
        }

        $ours = $request->input('externalId') ?: $request->input('reference');

        return $ours ? new PaymentResult('pending', $ours, $request->input('id'), null, 'callback', $request->all()) : null;
    }

    /** @param  array<string, mixed>  $data */
    protected function errorText(array $data, int $status): string
    {
        $message = (string) ($data['statusMessage'] ?? $data['message'] ?? $data['title'] ?? $data['detail'] ?? __('The payment could not be started (:code).', ['code' => $status]));
        if (static::sandbox() && stripos($message, 'test wallet') !== false) {
            $message .= ' '.__('Test mode only accepts ioTec test numbers such as 0111777771.');
        }

        return $message;
    }
}
