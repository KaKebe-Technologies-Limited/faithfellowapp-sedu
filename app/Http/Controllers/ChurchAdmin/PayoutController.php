<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Payout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Payout tracking: online gifts settled from the gateway to the church account.
 * Finance groups unsettled gifts into a payout and tracks its status.
 */
class PayoutController extends Controller
{
    public function index(Church $church): View
    {
        $unsettled = $church->donations()->succeeded()->whereNull('payout_id')->whereNotIn('method', ['cash', 'bank']);

        return view('church-admin.giving.payouts', [
            'church' => $church,
            'payouts' => $church->payouts()->withCount('donations')->latest('period_end')->paginate(20),
            'unsettledTotal' => (float) (clone $unsettled)->sum('amount'),
            'unsettledCount' => (clone $unsettled)->count(),
            'oldest' => (clone $unsettled)->min('paid_at'),
        ]);
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate(['until' => ['required', 'date', 'before_or_equal:today'], 'method' => ['nullable', 'string', 'max:40']]);
        $until = Carbon::parse($data['until'])->endOfDay();

        $payout = DB::transaction(function () use ($church, $until, $data) {
            $query = $church->donations()->succeeded()->whereNull('payout_id')->whereNotIn('method', ['cash', 'bank'])->where('paid_at', '<=', $until);
            $total = (clone $query)->sum('amount');
            abort_if($total <= 0, 422, __('There are no unsettled online gifts in that period.'));

            $payout = $church->payouts()->create([
                'amount' => $total, 'currency' => config('faithfellows.currency'),
                'period_start' => (clone $query)->min('paid_at'), 'period_end' => $until,
                'status' => 'pending', 'method' => $data['method'] ?? 'bank',
            ]);
            $query->update(['payout_id' => $payout->id]);

            return $payout;
        });

        return back()->with('success', __('Payout of :amount requested.', ['amount' => ff_money($payout->amount)]));
    }

    public function update(Request $request, Church $church, Payout $payout): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,paid,failed'],
            'reference' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $payout->update([...$data, 'paid_at' => $data['status'] === 'paid' ? ($payout->paid_at ?? now()) : null]);

        if ($data['status'] === 'failed') {
            $payout->donations()->update(['payout_id' => null]); // gifts become unsettled again
        }

        return back()->with('success', __('Payout updated.'));
    }
}
