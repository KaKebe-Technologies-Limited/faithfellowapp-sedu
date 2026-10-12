<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\PrayerRequest;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Prayer wall moderation. Moderators see every request, including pastor-only ones.
 */
class PrayerController extends Controller
{
    public function index(Request $request, Church $church): View
    {
        $status = $request->query('status');
        $visibility = $request->query('visibility');

        return view('church-admin.prayer.index', [
            'church' => $church,
            'prayers' => $church->prayerRequests()->with('user.media')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($visibility, fn ($q) => $q->where('visibility', $visibility))
                ->latest()->paginate(25)->withQueryString(),
            'counts' => $church->prayerRequests()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'pastorOnly' => $church->prayerRequests()->where('visibility', 'pastor')->where('status', 'open')->count(),
        ]);
    }

    public function update(Request $request, Church $church, PrayerRequest $prayerRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'hidden', 'answered'])]]);
        $prayerRequest->update([...$data, 'answered_at' => $data['status'] === 'answered' ? ($prayerRequest->answered_at ?? now()) : $prayerRequest->answered_at]);
        Audit::log('prayer.'.$data['status'], $prayerRequest);

        return back()->with('success', $data['status'] === 'hidden' ? __('Request hidden from the wall.') : __('Request updated.'));
    }

    public function destroy(Church $church, PrayerRequest $prayerRequest): RedirectResponse
    {
        $prayerRequest->delete();
        Audit::log('prayer.deleted', $prayerRequest);

        return back()->with('success', __('Prayer request deleted.'));
    }
}
