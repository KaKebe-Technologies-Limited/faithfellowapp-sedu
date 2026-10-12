<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Giving\GiveRequest;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\Donation;
use App\Models\EventRsvp;
use App\Models\GivingChannel;
use App\Models\Pledge;
use App\Models\RecurringGift;
use App\Models\SmallGroup;
use App\Services\DonationService;
use App\Services\Payments\Drivers\SandboxDriver;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Give flow: choose church → category / campaign → amount → method → pay.
 */
class GiveController extends Controller
{
    public function __construct(protected DonationService $donations) {}

    public function create(Request $request, Church $church): View
    {
        abort_unless($church->isActive(), 404);
        $church->load(['media', 'givingChannels' => fn ($q) => $q->where('is_active', true)]);

        $campaign = $request->filled('campaign')
            ? Campaign::with('teams')->where('church_id', $church->id)->where('slug', $request->query('campaign'))->first()
            : null;
        $pledge = $request->filled('pledge')
            ? Pledge::where('church_id', $church->id)->where('user_id', $request->user()->id)->find($request->query('pledge'))
            : null;

        return view('member.give.create', [
            'church' => $church,
            'campaign' => $campaign,
            'pledge' => $pledge,
            'group' => $request->filled('group')
                ? SmallGroup::where('church_id', $church->id)->whereHas('memberships', fn ($q) => $q->where('user_id', $request->user()->id))->find($request->query('group'))
                : null,
            'campaigns' => $church->campaigns()->active()->visibleTo($request->user()->memberChurchIds())->orderBy('title')->get(['id', 'title', 'slug']),
            'channels' => $church->givingChannels->filter(fn ($c) => PaymentService::enabled($c->type))->values(),
            'diaspora' => $this->isDiaspora($request),
            'savedPhone' => $request->user()->phone,
        ]);
    }

    public function store(GiveRequest $request, Church $church): RedirectResponse
    {
        $data = $request->validated();
        $data['country'] = $this->country($request);

        // Recurring giving: save the schedule; the first gift is charged now.
        if (! empty($data['recurring'])) {
            $recurring = RecurringGift::create([
                'church_id' => $church->id, 'user_id' => $request->user()->id, 'campaign_id' => $data['campaign_id'] ?? null,
                'category' => $data['category'] ?? 'tithe', 'amount' => $data['amount'], 'currency' => config('faithfellows.currency'),
                'frequency' => $data['recurring'], 'method' => $data['method'], 'phone' => $data['phone'] ?? null,
                'next_run_on' => today(), 'status' => 'active',
            ]);
            $recurring->update(['next_run_on' => $recurring->nextDateAfter(today()), 'last_run_at' => now()]);
            $data['recurring_gift_id'] = $recurring->id;
        }

        $result = $this->donations->initiate($request->user(), $church, $data);

        if ($result['result']->status === 'redirect') {
            return redirect()->away($result['result']->redirectUrl);
        }

        return redirect()->route('give.status', $result['donation']->reference);
    }

    /**
     * "Approve on your phone" waiting page; polls statusJson.
     */
    public function status(Request $request, string $reference): View
    {
        $donation = $this->findOwn($request, $reference);

        // Back from hosted checkout (Stripe) — confirm with the gateway right away.
        if ($request->filled('session_id') || $request->boolean('cancelled')) {
            if ($request->boolean('cancelled') && ! $donation->isFinal()) {
                $this->donations->markFailed($donation, __('Checkout was cancelled.'));
            }
            $donation = $this->donations->sync($donation->fresh());
        }

        return view('member.give.status', ['donation' => $donation->load(['church.media', 'campaign'])]);
    }

    public function statusJson(Request $request, string $reference): JsonResponse
    {
        $donation = $this->donations->sync($this->findOwn($request, $reference));

        return response()->json([
            'status' => $donation->status,
            'message' => $donation->failure_reason,
            'receipt' => $donation->receipt_number,
        ]);
    }

    /**
     * Local fake card checkout (sandbox only).
     */
    public function sandbox(Request $request, string $reference): View|RedirectResponse
    {
        $donation = $this->findOwn($request, $reference);
        abort_unless(PaymentService::isSandbox($donation->method), 404);

        if ($request->isMethod('post')) {
            /** @var SandboxDriver $driver */
            $driver = PaymentService::driver($donation->method);
            $driver->decide($donation->reference, $request->input('decision') === 'approve');
            $this->donations->sync($donation);

            return redirect()->route('give.status', $donation->reference);
        }

        return view('member.give.sandbox', ['donation' => $donation->load('church')]);
    }

    /**
     * Pay for reserved event tickets.
     */
    public function ticket(Request $request, EventRsvp $rsvp): View|RedirectResponse
    {
        abort_unless($rsvp->user_id === $request->user()->id, 403);
        $rsvp->load(['event.church.givingChannels', 'ticket']);

        if ($rsvp->status !== 'pending_payment') {
            return redirect()->route('events.show', $rsvp->event);
        }

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'method' => ['required', 'in:'.implode(',', GivingChannel::ONLINE)],
                'phone' => ['nullable', 'required_if:method,iotec,mtn_momo,airtel_money', 'regex:/^(\+?256|0)?7\d{8}$/'],
            ]);
            $result = $this->donations->initiate($request->user(), $rsvp->event->church, [
                ...$data, 'amount' => $rsvp->amount, 'category' => 'event', 'event_rsvp_id' => $rsvp->id, 'country' => $this->country($request),
            ]);

            return $result['result']->status === 'redirect'
                ? redirect()->away($result['result']->redirectUrl)
                : redirect()->route('give.status', $result['donation']->reference);
        }

        return view('member.give.ticket', ['rsvp' => $rsvp, 'channels' => $rsvp->event->church->givingChannels->where('is_active', true)->filter->isOnline()]);
    }

    protected function findOwn(Request $request, string $reference): Donation
    {
        $donation = Donation::with('church')->where('reference', $reference)->firstOrFail();
        abort_unless($donation->user_id === $request->user()->id, 403);

        return $donation;
    }

    /**
     * Diaspora mode: givers outside Uganda see card payments first.
     * Uses Cloudflare's CF-IPCountry header, falling back to the profile country.
     */
    protected function isDiaspora(Request $request): bool
    {
        return ! in_array($this->country($request), config('faithfellows.home_countries'), true);
    }

    protected function country(Request $request): string
    {
        $cf = strtoupper((string) $request->header('CF-IPCountry'));

        return strlen($cf) === 2 && $cf !== 'XX' ? $cf : strtoupper($request->user()?->country ?? 'UG');
    }
}
