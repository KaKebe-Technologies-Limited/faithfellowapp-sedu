<?php

namespace App\Contracts;

/**
 * Anything that can be paid through PaymentService (donations, ticket
 * purchases, church subscription invoices).
 */
interface Payable
{
    public function paymentReference(): string;

    public function paymentAmount(): float;

    public function paymentCurrency(): string;

    public function paymentDescription(): string;

    public function paymentPhone(): ?string;

    public function paymentEmail(): ?string;

    /** Where the giver returns after a hosted checkout (card). */
    public function paymentReturnUrl(): string;
}
