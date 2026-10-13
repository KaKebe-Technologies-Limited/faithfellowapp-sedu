<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Services\PodcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reviewing members' podcasts: a podcast shows in the app only after approval. The super admin can also say why one
 * was not approved, hide one later, and name the host sites podcasters may use for their audio.
 */
class PodcastController extends Controller
{
    public function __construct(protected PodcastService $podcasts) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $list = Podcast::query()->with('owner')->withCount('episodes')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->query('q').'%')->orWhere('host_name', 'like', '%'.$request->query('q').'%')))
            ->latest()->paginate(20)->withQueryString();

        return view('super-admin.podcasts.index', [
            'podcasts' => $list,
            'status' => $status,
            'counts' => Podcast::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'hosts' => implode(', ', $this->podcasts->allowedHosts()),
        ]);
    }

    public function show(Podcast $podcast): View
    {
        return view('super-admin.podcasts.show', ['podcast' => $podcast->load('owner'), 'episodes' => $podcast->episodes()->orderByDesc('published_at')->get()]);
    }

    public function approve(Podcast $podcast): RedirectResponse
    {
        $this->podcasts->approve($podcast);

        return back()->with('success', __('“:name” is approved and now in the app.', ['name' => $podcast->name]));
    }

    public function reject(Request $request, Podcast $podcast): RedirectResponse
    {
        $this->podcasts->reject($podcast, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);

        return back()->with('success', __('The podcaster was told why it was not approved.'));
    }

    public function hide(Request $request, Podcast $podcast): RedirectResponse
    {
        $this->podcasts->hide($podcast, $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null);

        return back()->with('success', __('The podcast is hidden from the app.'));
    }

    public function hosts(Request $request): RedirectResponse
    {
        $this->podcasts->saveHosts($request->validate(['hosts' => ['nullable', 'string', 'max:1000']])['hosts'] ?? '');

        return back()->with('success', __('Host sites saved.'));
    }
}
