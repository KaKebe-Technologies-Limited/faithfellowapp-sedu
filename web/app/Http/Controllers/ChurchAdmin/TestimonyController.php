<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Testimony;
use App\Notifications\Care\CommunityUpdate;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TestimonyController extends Controller
{
    public function index(Request $request, Church $church): View
    {
        $status = $request->query('status', 'pending');

        return view('church-admin.testimonies.index', [
            'church' => $church,
            'status' => $status,
            'testimonies' => $church->testimonies()->with(['user.media', 'prayerRequest'])
                ->where('status', $status)->latest()->paginate(20)->withQueryString(),
            'counts' => $church->testimonies()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function update(Request $request, Church $church, Testimony $testimony): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'pending'])]]);
        $testimony->update([...$data, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        Audit::log('testimony.'.$data['status'], $testimony);

        if ($data['status'] === 'approved') {
            $testimony->user?->notify(new CommunityUpdate($church, __('Your testimony ":t" is now live. Thank you for encouraging the church!', ['t' => $testimony->title]), route('testimonies.index'), null, 'bi-stars'));
        }

        return back()->with('success', $data['status'] === 'approved' ? __('Testimony published.') : __('Testimony updated.'));
    }

    public function destroy(Church $church, Testimony $testimony): RedirectResponse
    {
        $testimony->delete();
        Audit::log('testimony.deleted', $testimony);

        return back()->with('success', __('Testimony deleted.'));
    }
}
