<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DonationResource;
use App\Models\Church;
use App\Models\Donation;
use App\Models\GivingChannel;
use App\Services\DonationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Start a gift from the app and poll its status. Mobile money sends the
 * PIN prompt to the phone; cards return a hosted-checkout URL.
 */
class GivingController extends Controller
{
    public function __construct(protected DonationService $donations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return DonationResource::collection(Donation::where('user_id', $request->user()->id)->with(['church.media', 'campaign'])
            ->latest()->paginate(20));
    }

    public function store(Request $request, Church $church): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:500', 'max:100000000'],
            'category' => ['required_without:campaign_id', 'nullable', Rule::in(array_diff(array_keys(config('faithfellows.giving_categories')), ['event', 'pledge']))],
            'method' => ['required', Rule::in(GivingChannel::ONLINE), function ($attribute, $value, $fail) {
                if (! PaymentService::enabled((string) $value)) {
                    $fail(__('This payment method is temporarily unavailable. Please choose another.'));
                }
            }],
            'phone' => ['nullable', 'required_if:method,iotec,mtn_momo,airtel_money', 'regex:/^(\+?256|0)?7\d{8}$/'],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('church_id', $church->id)],
            'is_anonymous' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        ['donation' => $donation, 'result' => $result] = $this->donations->initiate($request->user(), $church, $data);

        return (new DonationResource($donation->load(['church.media', 'campaign'])))
            ->additional(['next' => [
                'action' => $result->status === 'redirect' ? 'redirect' : ($donation->status === 'succeeded' ? 'done' : 'approve_on_phone'),
                'redirect_url' => $result->redirectUrl,
                'message' => $result->message,
                'poll_url' => route('api.v1.giving.show', $donation->reference),
            ]])->response()->setStatusCode(201);
    }

    public function show(Request $request, string $reference): DonationResource
    {
        $donation = Donation::where('user_id', $request->user()->id)->where('reference', $reference)->firstOrFail();
        if (in_array($donation->status, ['pending', 'processing'], true)) {
            $donation = $this->donations->sync($donation);
        }

        return new DonationResource($donation->load(['church.media', 'campaign']));
    }
}
