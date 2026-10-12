<?php

namespace App\Services\Payments\Drivers;

use App\Contracts\Payable;
use App\Contracts\PaymentDriver;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Simulated gateway for local development, demos and tests.
 *
 *  - Mobile money: "approves" ~4 seconds after the prompt. Phone numbers ending
 *    in 0000 are declined, 1111 never respond (stay pending).
 *  - Card: redirects to a local fake checkout page with Pay / Decline buttons.
 */
class SandboxDriver implements PaymentDriver
{
    public function __construct(protected string $method) {}

    public static function configured(): bool
    {
        return true;
    }

    public function name(): string
    {
        return $this->method;
    }

    public function charge(Payable $payable): PaymentResult
    {
        $providerRef = 'SBX-'.Str::upper(Str::random(12));
        Cache::put($this->key($payable->paymentReference()), [
            'provider_ref' => $providerRef,
            'phone' => $payable->paymentPhone(),
            'started' => now()->timestamp,
            'decision' => null,
        ], now()->addDay());
        // Real gateways are verified by their own id, so map it back to our reference.
        Cache::put('sandbox_payment_ref:'.$providerRef, $payable->paymentReference(), now()->addDay());

        if ($this->method === 'stripe') {
            return PaymentResult::redirect($payable->paymentReference(), route('give.sandbox', $payable->paymentReference()), $providerRef);
        }

        return PaymentResult::pending($payable->paymentReference(), $providerRef, __('Sandbox: approve the prompt on :phone (simulated).', ['phone' => $payable->paymentPhone()]));
    }

    public function verify(string $reference): PaymentResult
    {
        $reference = Cache::get('sandbox_payment_ref:'.$reference, $reference);
        $state = Cache::get($this->key($reference));
        if (! $state) {
            return PaymentResult::failed($reference, __('Unknown sandbox transaction.'));
        }

        if ($state['decision'] === 'approve') {
            return PaymentResult::succeeded($reference, $state['provider_ref'], ['sandbox' => true]);
        }
        if ($state['decision'] === 'decline') {
            return PaymentResult::failed($reference, __('Payment declined (sandbox).'), [], $state['provider_ref']);
        }
        if ($this->method === 'stripe') {
            return PaymentResult::pending($reference, $state['provider_ref']);
        }

        $phone = (string) $state['phone'];
        if (str_ends_with($phone, '1111')) {
            return PaymentResult::pending($reference, $state['provider_ref']);
        }
        if (now()->timestamp - $state['started'] < 4) {
            return PaymentResult::pending($reference, $state['provider_ref'], __('Waiting for approval on your phone…'));
        }

        return str_ends_with($phone, '0000')
            ? PaymentResult::failed($reference, __('The payment was declined (insufficient balance).'), [], $state['provider_ref'])
            : PaymentResult::succeeded($reference, $state['provider_ref'], ['sandbox' => true]);
    }

    /** Used by the fake card checkout page. */
    public function decide(string $reference, bool $approve): void
    {
        $state = Cache::get($this->key($reference));
        if ($state) {
            $state['decision'] = $approve ? 'approve' : 'decline';
            Cache::put($this->key($reference), $state, now()->addDay());
        }
    }

    public function refund(Payable $payable, ?string $providerRef = null): PaymentResult
    {
        return PaymentResult::refunded($payable->paymentReference(), 'SBX-RF-'.Str::upper(Str::random(8)));
    }

    public function webhook(Request $request): ?PaymentResult
    {
        // Accept the reference field each real gateway uses (MTN, Airtel, Iotec, Stripe).
        $reference = (string) ($request->input('reference') ?? $request->input('externalId')
            ?? $request->input('transaction.id') ?? $request->input('data.object.client_reference_id'));

        return $reference ? new PaymentResult('pending', $reference, null, null, 'callback') : null;
    }

    protected function key(string $reference): string
    {
        return 'sandbox_payment:'.$reference;
    }
}
