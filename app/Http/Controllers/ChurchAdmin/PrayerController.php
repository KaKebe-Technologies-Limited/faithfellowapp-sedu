<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\PrayerRequest;
use App\Services\PrayerService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Prayer wall moderation: approve requests before they go to the whole
 * church, answer private requests, hide anything inappropriate.
 */
class PrayerController extends Controller
{
    public function __construct(protected PrayerService $prayers) {}

    public function index(Request $request, Church $church): View
    {
        $status = $request->query('status', 'pending');

        return view('church-admin.prayer.index', [
            'church' => $church,
            'status' => $status,
            'prayers' => $church->prayerRequests()->with(['user.media', 'responder'])
                ->when($status === 'pending', fn ($q) => $q->where('status', 'pending'))
                ->when($status === 'private', fn ($q) => $q->where('visibility', 'pastor')->whereNotIn('status', ['hidden']))
                ->when($status === 'wall', fn ($q) => $q->whereIn('status', PrayerRequest::SHARED)->where('visibility', '!=', 'pastor'))
                ->when(in_array($status, ['declined', 'hidden'], true), fn ($q) => $q->where('status', $status))
                ->latest()->paginate(25)->withQueryString(),
            'counts' => [
                'pending' => $church->prayerRequests()->where('status', 'pending')->count(),
                'private' => $church->prayerRequests()->where('visibility', 'pastor')->where('status', 'open')->whereNull('responded_at')->count(),
                'wall' => $church->prayerRequests()->whereIn('status', PrayerRequest::SHARED)->where('visibility', '!=', 'pastor')->count(),
            ],
        ]);
    }

    public function approve(Request $request, Church $church, PrayerRequest $prayerRequest): RedirectResponse
    {
        $this->prayers->approve($prayerRequest, $request->user());
        Audit::log('prayer.approved', $prayerRequest);

        return back()->with('success', __('Shared with the church — every member has been invited to pray.'));
    }

    public function decline(Request $request, Church $church, PrayerRequest $prayerRequest): RedirectResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $this->prayers->decline($prayerRequest, $request->user(), $note);
        Audit::log('prayer.declined', $prayerRequest);

        return back()->with('success', __('Kept private. The member has been told you are praying with them.'));
    }

    public function respond(Request $request, Church $church, PrayerRequest $prayerRequest): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:3000']]);
        $this->prayers->respond($prayerRequest, $request->user(), $data['response']);
        Audit::log('prayer.responded', $prayerRequest);

        return back()->with('success', __('Your reply was sent privately.'));
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
