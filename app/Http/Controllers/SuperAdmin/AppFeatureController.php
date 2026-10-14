<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Support\AppFeatures;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Podcasts, the hymn book and the gospel radio belong to the whole platform. Here the super admin decides, church by church, whose
 * members see them in the app. Everything is on for everyone until it is switched off; someone with no church yet always sees all
 * three (see App\Support\AppFeatures).
 */
class AppFeatureController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $active = Church::query()->where('status', 'active');

        $churches = (clone $active)
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('city', 'like', "%{$q}%")->orWhere('district', 'like', "%{$q}%")))
            ->orderBy('name')->paginate(50)->withQueryString();

        $totals = collect(AppFeatures::FEATURES)->map(fn (array $feature) => (clone $active)->where($feature['column'], true)->count());

        return view('super-admin.features.index', ['churches' => $churches, 'totals' => $totals, 'total' => (clone $active)->count(), 'q' => $q]);
    }

    /** Saves the churches shown on the page: a box ticked means the feature is on for that church. */
    public function update(Request $request): RedirectResponse
    {
        $ids = collect((array) $request->input('ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values();
        $changed = 0;

        foreach (Church::whereIn('id', $ids)->get() as $church) {
            $new = [];
            foreach (AppFeatures::FEATURES as $key => $feature) {
                $new[$feature['column']] = in_array($church->id, array_map('intval', (array) $request->input("on.{$key}", [])), true);
            }
            $old = $church->only(array_keys($new));
            if ($old !== $new) {
                $church->forceFill($new)->saveQuietly();
                Audit::log('church.features', $church, __('App features changed for :church', ['church' => $church->name]), $old, $new, $church->id);
                $changed++;
            }
        }

        return back()->with('status', $changed ? __(':count church(es) updated.', ['count' => $changed]) : __('Nothing to change.'));
    }

    /** One feature on or off for every church at once. */
    public function all(Request $request): RedirectResponse
    {
        $data = $request->validate(['feature' => ['required', Rule::in(array_keys(AppFeatures::FEATURES))], 'value' => ['required', 'boolean']]);
        $feature = AppFeatures::FEATURES[$data['feature']];

        Church::query()->update([$feature['column'] => (bool) $data['value']]);
        Audit::log('church.features.all', null, __(':feature switched :state for every church', ['feature' => $feature['label'], 'state' => $data['value'] ? 'on' : 'off']));

        return back()->with('status', __(':feature is now :state for every church.', ['feature' => __($feature['label']), 'state' => $data['value'] ? __('on') : __('off')]));
    }
}
