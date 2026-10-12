<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Donation;
use App\Models\Pledge;
use App\Models\RecurringGift;
use App\Services\CampaignService;
use App\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Member giving history, receipts, pledges and recurring gifts.
 */
class GivingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $donations = Donation::with(['church.media', 'campaign'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['succeeded', 'pending', 'processing', 'refunded', 'failed'])
            ->when($request->filled('year'), fn ($q) => $q->whereYear('created_at', $request->query('year')))
            ->latest()
            ->paginate(20)->withQueryString();

        $totals = Donation::where('user_id', $user->id)->succeeded()->whereYear('paid_at', now()->year)
            ->selectRaw('church_id, SUM(amount) as total')->groupBy('church_id')->pluck('total', 'church_id');

        return view('member.giving.index', [
            'donations' => $donations,
            'yearTotal' => $totals->sum(),
            'byChurch' => Church::whereIn('id', $totals->keys())->with('media')->get()->map(fn ($c) => ['church' => $c, 'total' => $totals[$c->id]]),
            'pledges' => Pledge::with(['church.media', 'campaign'])->where('user_id', $user->id)->whereIn('status', ['active', 'overdue'])->latest()->get(),
            'recurring' => RecurringGift::with('church.media')->where('user_id', $user->id)->whereIn('status', ['active', 'paused'])->get(),
            'churches' => Church::whereIn('id', $user->subscribedChurchIds())->with('media')->orderBy('name')->get(),
        ]);
    }

    public function receipt(Request $request, string $reference, ReceiptService $receipts): BinaryFileResponse
    {
        $donation = Donation::where('reference', $reference)->firstOrFail();
        $this->authorize('view', $donation);
        abort_unless(in_array($donation->status, ['succeeded', 'refunded'], true) && $donation->receipt_number, 404);

        return response()->download($receipts->path($donation), $receipts->filename($donation), ['Content-Type' => 'application/pdf']);
    }

    public function storePledge(Request $request, Church $church): RedirectResponse
    {
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

        return redirect()->route('giving.index')->with('success', __('Thank you for pledging :amount. We’ll send gentle reminders — pay any time from your giving page.', ['amount' => ff_money($pledge->amount)]));
    }

    public function updateRecurring(Request $request, RecurringGift $recurringGift): RedirectResponse
    {
        abort_unless($recurringGift->user_id === $request->user()->id, 403);
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'paused', 'cancelled'])]]);
        $recurringGift->update($data);

        return back()->with('success', match ($data['status']) {
            'paused' => __('Recurring gift paused.'),
            'cancelled' => __('Recurring gift cancelled.'),
            default => __('Recurring gift resumed.'),
        });
    }

    public function cancelPledge(Request $request, Pledge $pledge): RedirectResponse
    {
        abort_unless($pledge->user_id === $request->user()->id, 403);
        $pledge->update(['status' => 'cancelled']);

        return back()->with('success', __('Pledge cancelled.'));
    }
}
