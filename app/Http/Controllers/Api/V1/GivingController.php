<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithPayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Giving\GiveRequest;
use App\Http\Resources\DonationResource;
use App\Models\Church;
use App\Models\Donation;
use App\Models\GivingChannel;
use App\Models\Pledge;
use App\Models\RecurringGift;
use App\Services\CampaignService;
use App\Services\DonationService;
use App\Services\Payments\Drivers\IotecDriver;
use App\Services\PaymentService;
use App\Support\MobileMoneyPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Giving from the app: what a church accepts, start a gift (mobile money sends the PIN prompt to the phone,
 * cards return a hosted-checkout URL), poll its status, read receipts, and manage pledges and recurring gifts.
 */
class GivingController extends Controller
{
    use RespondsWithPayment;

    public function __construct(protected DonationService $donations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return DonationResource::collection(Donation::where('user_id', $request->user()->id)->with(['church.media', 'campaign'])
            ->whereIn('status', ['succeeded', 'pending', 'processing', 'refunded', 'failed'])
            ->when($request->filled('year'), fn ($q) => $q->whereYear('created_at', (int) $request->query('year')))
            ->latest()->paginate(20)->withQueryString());
    }

    /** The giving page header: this year's total per church, open pledges and recurring gifts. */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $totals = Donation::where('user_id', $user->id)->succeeded()->whereYear('paid_at', now()->year)
            ->selectRaw('church_id, SUM(amount) as total')->groupBy('church_id')->pluck('total', 'church_id');

        return response()->json(['data' => [
            'year' => now()->year,
            'currency' => config('faithfellows.currency'),
            'year_total' => (float) $totals->sum(),
            'by_church' => Church::whereIn('id', $totals->keys())->with('media')->get()->map(fn (Church $c) => [
                'church' => ['id' => $c->id, 'slug' => $c->slug, 'name' => $c->name, 'logo_url' => $c->logo_url], 'total' => (float) $totals[$c->id],
            ])->values(),
            'pledges' => Pledge::with(['church.media', 'campaign'])->where('user_id', $user->id)->whereIn('status', ['active', 'overdue'])->latest()->get()->map(fn (Pledge $p) => $this->pledge($p))->values(),
            'recurring' => RecurringGift::with('church.media')->where('user_id', $user->id)->whereIn('status', ['active', 'paused'])->get()->map(fn (RecurringGift $r) => $this->recurring($r))->values(),
        ]]);
    }

    /** What the give screen needs for one church: methods it accepts, categories, open campaigns, quick amounts. */
    public function options(Request $request, Church $church): JsonResponse
    {
        abort_unless($church->isActive(), 404);
        $user = $request->user();
        $country = strtoupper((string) ($user->country ?: 'UG'));
        $channels = $church->givingChannels()->where('is_active', true)->orderBy('sort')->get();
        // When ioTec Pay carries MTN and Airtel anyway, one "Mobile money" button is clearer than three that end up in the same place.
        $oneMobileMoney = $channels->contains('type', 'iotec') && PaymentService::driver('mtn_momo') instanceof IotecDriver;

        return response()->json(['data' => [
            'church' => ['id' => $church->id, 'slug' => $church->slug, 'name' => $church->name, 'logo_url' => $church->logo_url],
            'currency' => config('faithfellows.currency'),
            'methods' => $channels
                ->filter(fn (GivingChannel $c) => $c->isOnline() && PaymentService::enabled($c->type))
                // When real money is switched on, a gateway that has no credentials (and would only pretend to take a payment) is not offered.
                ->reject(fn (GivingChannel $c) => ! config('faithfellows.payments.sandbox') && PaymentService::isSandbox($c->type))
                ->reject(fn (GivingChannel $c) => $oneMobileMoney && in_array($c->type, ['mtn_momo', 'airtel_money'], true))
                ->map(fn (GivingChannel $c) => ['type' => $c->type, 'label' => $c->display_name, 'needs_phone' => in_array($c->type, GivingChannel::PHONE_METHODS, true), 'phone_pattern' => MobileMoneyPhone::pattern()])->values(),
            'offline' => $church->givingChannels()->where('is_active', true)->whereIn('type', ['bank', 'cash', 'ussd'])->orderBy('sort')->get()
                ->map(fn (GivingChannel $c) => ['type' => $c->type, 'label' => $c->display_name, 'instructions' => $c->instructions])->values(),
            // What the church lets people give for (the church writes this list); `categories` is kept for older apps.
            'reasons' => $church->givingReasons()->active()->ordered()->get()->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'description' => $r->description])->values(),
            'categories' => collect(config('faithfellows.giving_categories'))->except(['event', 'pledge', 'campaign'])->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'campaigns' => $church->campaigns()->active()->visibleTo($user->memberChurchIds())->orderBy('title')->get(['id', 'title'])->map(fn ($c) => ['id' => $c->id, 'title' => $c->title])->values(),
            'quick_amounts' => config('faithfellows.quick_amounts'),
            'saved_phone' => $user->phone,
            'diaspora' => ! in_array($country, config('faithfellows.home_countries'), true),
        ]]);
    }

    public function store(GiveRequest $request, Church $church): JsonResponse
    {
        $data = $request->validated();
        $data['country'] = strtoupper((string) ($request->user()->country ?: 'UG'));
        $data['return_to'] = 'app';

        if (! empty($data['recurring'])) {
            $data['recurring_gift_id'] = $this->donations->scheduleRecurring($request->user(), $church, $data)->id;
        }

        ['donation' => $donation, 'result' => $result] = $this->donations->initiate($request->user(), $church, $data);

        return $this->paymentStarted($donation, $result);
    }

    public function show(Request $request, string $reference): DonationResource
    {
        $donation = Donation::where('user_id', $request->user()->id)->where('reference', $reference)->firstOrFail();
        if (in_array($donation->status, ['pending', 'processing'], true)) {
            $donation = $this->donations->sync($donation);
        }

        return new DonationResource($donation->load(['church.media', 'campaign']));
    }

    /** The receipt as data, for the in-app receipt screen. */
    public function receipt(Request $request, string $reference): JsonResponse
    {
        $donation = Donation::with(['church.media', 'campaign'])->where('user_id', $request->user()->id)->where('reference', $reference)->firstOrFail();
        abort_unless(in_array($donation->status, ['succeeded', 'refunded'], true) && $donation->receipt_number, 404);

        return response()->json(['data' => [
            'receipt_number' => $donation->receipt_number,
            'status' => $donation->status,
            'amount' => (float) $donation->amount,
            'currency' => $donation->currency,
            'category' => $donation->category_label,
            'campaign' => $donation->campaign?->title,
            'method' => $donation->method_label,
            'reference' => $donation->reference,
            'giver' => $request->user()->name,
            'paid_at' => $donation->paid_at?->toIso8601String(),
            'church' => ['id' => $donation->church->id, 'slug' => $donation->church->slug, 'name' => $donation->church->name, 'logo_url' => $donation->church->logo_url],
        ]]);
    }

    public function storePledge(Request $request, Church $church): JsonResponse
    {
        abort_unless($church->isActive(), 404);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'campaign_id' => ['nullable', Rule::exists('campaigns', 'id')->where('church_id', $church->id)],
            'category' => ['nullable', Rule::in(['project', 'building_fund', 'missions', 'welfare', 'special', 'campaign'])],
            'frequency' => ['required', Rule::in(['once', 'weekly', 'monthly', 'quarterly'])],
            'installments' => ['required', 'integer', 'min:1', 'max:60'],
            'due_date' => ['nullable', 'date', 'after:today'],
            'is_anonymous' => ['boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $pledge = Pledge::create([
            ...$data,
            'church_id' => $church->id,
            'user_id' => $request->user()->id,
            'category' => ! empty($data['campaign_id']) ? 'campaign' : ($data['category'] ?? 'project'),
            'currency' => config('faithfellows.currency'),
            'start_date' => today(),
            'next_reminder_on' => today()->addWeek(),
        ]);

        if ($pledge->campaign) {
            app(CampaignService::class)->recalculate($pledge->campaign);
        }

        return response()->json(['data' => $this->pledge($pledge->load(['church.media', 'campaign']))], 201);
    }

    public function cancelPledge(Request $request, Pledge $pledge): JsonResponse
    {
        abort_unless($pledge->user_id === $request->user()->id, 403);
        $pledge->update(['status' => 'cancelled']);

        return response()->json(['data' => $this->pledge($pledge->load(['church.media', 'campaign']))]);
    }

    public function updateRecurring(Request $request, RecurringGift $recurringGift): JsonResponse
    {
        abort_unless($recurringGift->user_id === $request->user()->id, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'paused', 'cancelled'])]]);
        $recurringGift->update($data);

        return response()->json(['data' => $this->recurring($recurringGift->load('church.media'))]);
    }

    /** @return array<string, mixed> */
    protected function pledge(Pledge $p): array
    {
        return [
            'id' => $p->id, 'amount' => (float) $p->amount, 'paid' => (float) $p->paid_amount, 'currency' => $p->currency,
            'frequency' => $p->frequency, 'installments' => (int) $p->installments, 'status' => $p->status,
            'due_date' => $p->due_date?->toDateString(), 'campaign' => $p->campaign ? ['id' => $p->campaign->id, 'title' => $p->campaign->title] : null,
            'church' => ['id' => $p->church->id, 'slug' => $p->church->slug, 'name' => $p->church->name, 'logo_url' => $p->church->logo_url],
        ];
    }

    /** @return array<string, mixed> */
    protected function recurring(RecurringGift $r): array
    {
        return [
            'id' => $r->id, 'amount' => (float) $r->amount, 'currency' => $r->currency, 'frequency' => $r->frequency, 'method' => $r->method,
            'status' => $r->status, 'next_run_on' => $r->next_run_on?->toDateString(),
            'church' => ['id' => $r->church->id, 'slug' => $r->church->slug, 'name' => $r->church->name, 'logo_url' => $r->church->logo_url],
        ];
    }
}
