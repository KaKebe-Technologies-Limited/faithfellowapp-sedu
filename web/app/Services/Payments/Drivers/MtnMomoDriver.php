<?php

namespace App\Services\Payments\Drivers;

use App\Contracts\Payable;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * MTN Mobile Money — Collections API (RequestToPay).
 * Docs: https://momodeveloper.mtn.com/api-documentation
 */
class MtnMomoDriver extends GatewayDriver
{
    protected function configKey(): string
    {
        return 'mtn_momo';
    }

    public static function configured(): bool
    {
        return filled(config('services.mtn_momo.subscription_key')) && filled(config('services.mtn_momo.api_user')) && filled(config('services.mtn_momo.api_key'));
    }

    public function name(): string
    {
        return 'mtn_momo';
    }

    protected function token(): string
    {
        return $this->cachedToken(fn () => $this->http()
            ->withBasicAuth($this->config('api_user'), $this->config('api_key'))
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $this->config('subscription_key')])
            ->post($this->config('base_url').'/collection/token/')
            ->json());
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token(),
            'X-Target-Environment' => $this->config('environment', 'sandbox'),
            'Ocp-Apim-Subscription-Key' => $this->config('subscription_key'),
        ];
    }

    public function charge(Payable $payable): PaymentResult
    {
        $referenceId = (string) Str::uuid(); // MTN requires a UUID X-Reference-Id
        $currency = $this->config('environment') === 'sandbox' ? $this->config('currency', 'EUR') : $payable->paymentCurrency();

        $response = $this->http()
            ->withHeaders($this->headers() + array_filter([
                'X-Reference-Id' => $referenceId,
                'X-Callback-Url' => str_starts_with(config('app.url'), 'https') ? route('webhooks.payments', 'mtn_momo') : null,
            ]))
            ->post($this->config('base_url').'/collection/v1_0/requesttopay', [
                'amount' => (string) (int) round($payable->paymentAmount()),
                'currency' => $currency,
                'externalId' => $payable->paymentReference(),
                'payer' => ['partyIdType' => 'MSISDN', 'partyId' => static::msisdn($payable->paymentPhone())],
                'payerMessage' => Str::limit($payable->paymentDescription(), 160, ''),
                'payeeNote' => $payable->paymentReference(),
            ]);

        return $response->status() === 202
            ? PaymentResult::pending($payable->paymentReference(), $referenceId, __('Approve the MTN MoMo prompt on your phone and enter your PIN.'))
            : PaymentResult::failed($payable->paymentReference(), $response->json('message') ?? __('MTN MoMo request failed.'), (array) $response->json());
    }

    /**
     * @param  string  $reference  the X-Reference-Id (provider_ref) we stored at charge time
     */
    public function verify(string $reference): PaymentResult
    {
        $response = $this->http()->withHeaders($this->headers())
            ->get($this->config('base_url').'/collection/v1_0/requesttopay/'.$reference);
        $data = (array) $response->json();
        $ours = $data['externalId'] ?? null;

        return match ($data['status'] ?? null) {
            'SUCCESSFUL' => PaymentResult::succeeded($ours, $reference, $data),
            'FAILED', 'REJECTED', 'TIMEOUT' => PaymentResult::failed($ours, $data['reason'] ?? __('Payment was not completed.'), $data, $reference),
            default => PaymentResult::pending($ours, $reference),
        };
    }

    public function refund(Payable $payable, ?string $providerRef = null): PaymentResult
    {
        // Collections cannot reverse automatically; refunds are paid out via the Disbursements product.
        return PaymentResult::failed($payable->paymentReference(), __('MTN MoMo refunds are processed manually by finance (disbursement).'));
    }

    public function webhook(Request $request): ?PaymentResult
    {
        $ours = $request->input('externalId');

        return $ours ? new PaymentResult('pending', $ours, null, null, 'callback', $request->all()) : null;
    }
}
