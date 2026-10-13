<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Rules\NoContactDetails;
use App\Services\PodcastFeedException;
use App\Services\PodcastFeedService;
use App\Services\PodcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The podcast studio on the website: any member can start a faith-based podcast (a name, a summary, a picture) and
 * add episodes by pasting the link to the audio on a host site. The super admin reviews a podcast before it shows in
 * the app, where listeners search it, follow it and play it while they read, take notes and chat.
 */
class PodcastController extends Controller
{
    public function __construct(protected PodcastService $podcasts, protected PodcastFeedService $feeds) {}

    public function index(Request $request): View
    {
        return view('member.podcasts.index', [
            'podcasts' => $request->user()->podcasts()->withCount(['episodes as episodes_total'])->latest()->get(),
            'hosts' => $this->podcasts->allowedHosts(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('member.podcasts.form', ['podcast' => new Podcast(['host_name' => $request->user()->name, 'language' => 'en']), 'hosts' => $this->podcasts->allowedHosts()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, creating: true);
        $podcast = $request->user()->podcasts()->create([...$data, 'status' => 'pending']);
        if ($request->hasFile('cover')) {
            $podcast->addMediaFromRequest('cover')->toMediaCollection('cover');
        }

        return redirect()->route('podcasts.show', $podcast)->with('success', __('Your podcast was sent for review. Add your first episodes now: they appear in the app once it is approved.'));
    }

    /** Bring a podcast over from the RSS feed of the host it already uses. */
    public function importForm(Request $request): View
    {
        return view('member.podcasts.import', ['hostName' => $request->user()->name]);
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'feed_url' => ['required', 'url:https', 'max:500'],
            'host_name' => ['required', 'string', 'max:120', new NoContactDetails],
            'category' => ['nullable', Rule::in(array_keys(Podcast::CATEGORIES))],
            'faith_based' => ['accepted'],
        ], [
            'feed_url.url' => __('Use the full https link of your podcast RSS feed.'),
            'faith_based.accepted' => __('Please confirm that your podcast is faith-based and that you have the right to share the audio.'),
        ]);

        if (Podcast::withTrashed()->where('feed_url', $data['feed_url'])->exists()) {
            return back()->withInput()->withErrors(['feed_url' => __('This feed is already on FaithFellows.')]);
        }

        try {
            $podcast = $this->feeds->import($request->user(), $data['feed_url'], array_filter(['host_name' => $data['host_name'], 'category' => $data['category'] ?? null]));
        } catch (PodcastFeedException $e) {
            return back()->withInput()->withErrors(['feed_url' => $e->getMessage()]);
        }

        return redirect()->route('podcasts.show', $podcast)->with('success', $podcast->isListed()
            ? __('Imported :count episode(s). Your podcast is live in the app.', ['count' => $podcast->episodes()->count()])
            : __('Imported :count episode(s). Your podcast was sent for review and appears in the app once it is approved.', ['count' => $podcast->episodes()->count()]));
    }

    /** Check the feed again now instead of waiting for the next automatic check. */
    public function sync(Request $request, Podcast $podcast): RedirectResponse
    {
        $this->own($request, $podcast);
        abort_unless($podcast->feed_url, 404);
        $result = $this->feeds->sync($podcast);

        return back()->with($result['error'] ? 'error' : 'success', $result['error'] ?: __(':new new, :changed updated, :removed removed.', ['new' => $result['added'], 'changed' => $result['updated'], 'removed' => $result['removed']]));
    }

    /** Stop following the feed; the episodes stay and can be edited by hand. */
    public function disconnect(Request $request, Podcast $podcast): RedirectResponse
    {
        $this->own($request, $podcast);
        $podcast->update(['feed_url' => null, 'feed_error' => null, 'feed_synced_at' => null]);

        return back()->with('success', __('The feed is disconnected. Your episodes stay; change them here from now on.'));
    }

    public function show(Request $request, Podcast $podcast): View
    {
        $this->own($request, $podcast);

        return view('member.podcasts.show', ['podcast' => $podcast, 'episodes' => $podcast->episodes()->orderByDesc('published_at')->orderByDesc('id')->get(), 'hosts' => $this->podcasts->allowedHosts()]);
    }

    public function edit(Request $request, Podcast $podcast): View
    {
        $this->own($request, $podcast);

        return view('member.podcasts.form', ['podcast' => $podcast, 'hosts' => $this->podcasts->allowedHosts()]);
    }

    public function update(Request $request, Podcast $podcast): RedirectResponse
    {
        $this->own($request, $podcast);
        $podcast->update($this->validated($request));
        if ($request->hasFile('cover')) {
            $podcast->addMediaFromRequest('cover')->toMediaCollection('cover');
        }
        // A podcast that was not approved goes back for review once it has been changed.
        if ($podcast->status === 'rejected') {
            $podcast->update(['status' => 'pending']);
        }

        return redirect()->route('podcasts.show', $podcast)->with('success', $podcast->status === 'pending' ? __('Saved and sent for review again.') : __('Saved.'));
    }

    public function destroy(Request $request, Podcast $podcast): RedirectResponse
    {
        $this->own($request, $podcast);
        $podcast->delete();

        return redirect()->route('podcasts.index')->with('success', __('The podcast was deleted.'));
    }

    protected function own(Request $request, Podcast $podcast): void
    {
        abort_unless($podcast->user_id === $request->user()->id, 404);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, bool $creating = false): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160', new NoContactDetails],
            'host_name' => ['required', 'string', 'max:120', new NoContactDetails],
            'summary' => ['required', 'string', 'min:20', 'max:500', new NoContactDetails],
            'description' => ['nullable', 'string', 'max:5000', new NoContactDetails],
            'category' => ['required', Rule::in(array_keys(Podcast::CATEGORIES))],
            'language' => ['required', Rule::in(array_keys(config('faithfellows.languages')))],
            'website_url' => ['nullable', 'url:https,http', 'max:300'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'faith_based' => $creating ? ['accepted'] : ['sometimes'],
        ], ['faith_based.accepted' => __('Please confirm that your podcast is faith-based and that you have the right to share the audio.')]);

        return collect($data)->except(['cover', 'faith_based'])->all();
    }
}
