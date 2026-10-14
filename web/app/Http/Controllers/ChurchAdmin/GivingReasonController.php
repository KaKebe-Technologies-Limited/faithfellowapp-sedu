<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\GivingReason;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Giving reasons: the list of what members can give for at this church (Tithe, Offering, Building fund, a thanksgiving…).
 * Only the church's own staff change it. A gift keeps the reason's name, so renaming or removing one never changes past records.
 */
class GivingReasonController extends Controller
{
    /** The platform categories a reason can count as, for reports. */
    protected function categories(): array
    {
        return collect(config('faithfellows.giving_categories'))->except(['event', 'pledge', 'campaign'])->all();
    }

    public function index(Church $church): View
    {
        $totals = $church->donations()->getQuery()->succeeded()->whereNotNull('giving_reason_id')
            ->selectRaw('giving_reason_id, COUNT(*) as gifts, SUM(amount) as total')->groupBy('giving_reason_id')->get()->keyBy('giving_reason_id');

        return view('church-admin.giving.reasons', [
            'church' => $church,
            'reasons' => $church->givingReasons()->ordered()->get(),
            'totals' => $totals,
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $data = $this->validated($request, $church);
        $reason = $church->givingReasons()->create([...$data, 'is_active' => true, 'sort' => (int) $church->givingReasons()->max('sort') + 1]);
        Audit::log('giving_reason.created', $reason, $reason->name);

        return back()->with('success', __('“:name” is now something members can give for.', ['name' => $reason->name]));
    }

    public function update(Request $request, Church $church, GivingReason $givingReason): RedirectResponse
    {
        $reason = $givingReason;
        abort_unless($reason->church_id === $church->id, 404);
        $data = $this->validated($request, $church, $reason);
        $reason->update([...$data, 'is_active' => $request->boolean('is_active')]);
        Audit::log('giving_reason.updated', $reason, $reason->name);

        return back()->with('success', __('Saved.'));
    }

    public function destroy(Church $church, GivingReason $givingReason): RedirectResponse
    {
        $reason = $givingReason;
        abort_unless($reason->church_id === $church->id, 404);
        $name = $reason->name;
        $reason->delete();
        Audit::log('giving_reason.deleted', null, $name);

        return back()->with('success', __('“:name” was removed. Gifts already given keep its name.', ['name' => $name]));
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, Church $church, ?GivingReason $reason = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('giving_reasons', 'name')->where('church_id', $church->id)->ignore($reason?->id)],
            'category' => ['required', Rule::in(array_keys($this->categories()))],
            'description' => ['nullable', 'string', 'max:160'],
        ]);
    }
}
