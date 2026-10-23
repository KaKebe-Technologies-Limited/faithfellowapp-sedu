<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\SubscriptionInvoice;
use App\Services\BillingService;
use App\Services\DonationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * /webhooks/{provider} — mtn-momo, airtel, iotec, stripe.
 *
 *  1. The driver verifies the signature (Stripe, optional HMAC for others)
 *     and extracts OUR reference.
 *  2. We never trust the callback body for money: mobile-money gifts are
 *     re-verified with the gateway API (DonationService::sync()).
 *  3. Processing is idempotent (provider_ref unique + row lock), so retries
 *     and duplicate callbacks are harmless.
 */
class PaymentWebhookController extends Controller
{
    /** URL slug → method key. */
    protected const PROVIDERS = [
        'mtn-momo' => 'mtn_momo', 'mtn_momo' => 'mtn_momo',
        'airtel' => 'airtel_money', 'airtel_money' => 'airtel_money',
        'iotec' => 'iotec',
        'stripe' => 'stripe',
    ];

    public function __invoke(Request $request, string $provider, DonationService $donations, BillingService $billing): JsonResponse
    {
        $method = self::PROVIDERS[$provider] ?? abort(404);
        $result = PaymentService::driver($method)->webhook($request);

        if (! $result || ! $result->reference) {
            Log::warning('Ignored payment webhook', ['provider' => $provider]);

            return response()->json(['received' => true, 'handled' => false], $result === null && $method === 'stripe' ? 400 : 200);
        }

        if ($donation = Donation::where('reference', $result->reference)->first()) {
            if (! $donation->provider_ref && $result->providerRef && ! Donation::where('provider_ref', $result->providerRef)->exists()) {
                $donation->update(['provider_ref' => $result->providerRef]);
            }
            // Stripe events are signature-verified; mobile money is re-checked with the API.
            $method === 'stripe' && $result->isFinal()
                ? $donations->applyResult($donation, $result)
                : $donations->sync($donation);

            return response()->json(['received' => true, 'status' => $donation->fresh()->status]);
        }

        if ($invoice = SubscriptionInvoice::where('reference', $result->reference)->first()) {
            $billing->syncInvoicePayment($invoice, $method, $result);

            return response()->json(['received' => true, 'status' => $invoice->fresh()->status]);
        }

        return response()->json(['received' => true, 'handled' => false]);
    }
}
