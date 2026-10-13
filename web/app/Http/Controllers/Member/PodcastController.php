<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Rules\NoContactDetails;
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
    public function __construct(protected PodcastService $podcasts) {}

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
