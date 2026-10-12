<?php

namespace App\Services\Payments\Drivers;

use App\Contracts\Payable;
use App\Contracts\PaymentDriver;
use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * Bank transfer and cash: no online gateway.
 *  - bank: the gift stays pending until finance reconciles the bank statement.
 *  - cash: recorded by finance staff as received.
 */
class ManualDriver implements PaymentDriver
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
        return $this->method === 'cash'
            ? PaymentResult::succeeded($payable->paymentReference(), null, ['manual' => true])
            : PaymentResult::pending($payable->paymentReference(), null, __('Use reference :ref on your bank transfer. We confirm it when it reaches the church account.', ['ref' => $payable->paymentReference()]));
    }

    public function verify(string $reference): PaymentResult
    {
        return PaymentResult::pending($reference);
    }

    public function refund(Payable $payable, ?string $providerRef = null): PaymentResult
    {
        // Refunded outside the platform (cash returned / bank transfer back).
        return PaymentResult::refunded($payable->paymentReference(), $providerRef);
    }

    public function webhook(Request $request): ?PaymentResult
    {
        return null;
    }
}
