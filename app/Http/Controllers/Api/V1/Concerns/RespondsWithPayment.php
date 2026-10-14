<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Resources\DonationResource;
use App\Models\Donation;
use App\Services\Payments\PaymentResult;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

/**
 * The shared answer after a payment has been started from the app (a gift or a ticket): the donation plus
 * what the app does next — wait for the PIN prompt on the phone, open the hosted card page, or show "done".
 */
trait RespondsWithPayment
{
    protected function paymentStarted(Donation $donation, PaymentResult $result): JsonResponse
    {
        return (new DonationResource($donation->load(['church.media', 'campaign'])))
            ->additional(['next' => [
                'action' => $result->status === 'redirect' ? 'redirect' : ($donation->status === 'succeeded' ? 'done' : 'approve_on_phone'),
                'redirect_url' => $result->redirectUrl,
                'message' => $result->message,
                'poll_url' => route('api.v1.giving.show', $donation->reference),
                'gateway' => rescue(fn () => PaymentService::driver($donation->method)->name(), null, false),
            ]])->response()->setStatusCode(201);
    }
}
