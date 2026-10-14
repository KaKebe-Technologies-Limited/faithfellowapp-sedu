<?php

namespace App\Contracts;

use App\Services\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * One payment gateway (MTN MoMo, Airtel Money, Iotec, Stripe…).
 *
 * Usage: PaymentService::driver('mtn_momo')->charge($donation);
 */
interface PaymentDriver
{
    public function name(): string;

    /** Start a payment (mobile-money prompt or hosted checkout). */
    public function charge(Payable $payable): PaymentResult;

    /** Ask the gateway for the current status of one of our references. */
    public function verify(string $reference): PaymentResult;

    /** Refund a completed payment. */
    public function refund(Payable $payable, ?string $providerRef = null): PaymentResult;

    /**
     * Verify and parse an incoming webhook. Returns null for events we ignore.
     * The result always carries OUR reference so the caller can find the record.
     */
    public function webhook(Request $request): ?PaymentResult;
}
