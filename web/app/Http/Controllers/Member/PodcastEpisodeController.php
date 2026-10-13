<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Podcast;
use App\Models\PodcastEpisode;
use App\Rules\NoContactDetails;
use App\Services\PodcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Adding, changing and removing the episodes of a member's podcast. The audio itself stays on the host site: only its link is kept. */
class PodcastEpisodeController extends Controller
{
    public function __construct(protected PodcastService $podcasts) {}

    public function create(Request $request, Podcast $podcast): View
    {
        $this->own($request, $podcast);

        return view('member.podcasts.episode-form', ['podcast' => $podcast, 'episode' => new PodcastEpisode(['status' => 'published']), 'hosts' => $this->podcasts->allowedHosts()]);
    }

    public function store(Request $request, Podcast $podcast): RedirectResponse
    {
        $this->own($request, $podcast);
        $podcast->episodes()->create($this->validated($request));

        return redirect()->route('podcasts.show', $podcast)->with('success', $podcast->isListed() ? __('Episode added. It is in the app now.') : __('Episode added. It will appear in the app once your podcast is approved.'));
    }

    public function edit(Request $request, Podcast $podcast, PodcastEpisode $episode): View
    {
        $this->own($request, $podcast, $episode);

        return view('member.podcasts.episode-form', ['podcast' => $podcast, 'episode' => $episode, 'hosts' => $this->podcasts->allowedHosts()]);
    }

    public function update(Request $request, Podcast $podcast, PodcastEpisode $episode): RedirectResponse
    {
        $this->own($request, $podcast, $episode);
        $episode->update($this->validated($request, $episode));

        return redirect()->route('podcasts.show', $podcast)->with('success', __('Episode saved.'));
    }

    public function destroy(Request $request, Podcast $podcast, PodcastEpisode $episode): RedirectResponse
    {
        $this->own($request, $podcast, $episode);
        $episode->delete();

        return redirect()->route('podcasts.show', $podcast)->with('success', __('Episode deleted.'));
    }

    protected function own(Request $request, Podcast $podcast, ?PodcastEpisode $episode = null): void
    {
        abort_unless($podcast->user_id === $request->user()->id && (! $episode || $episode->podcast_id === $podcast->id), 404);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?PodcastEpisode $existing = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180', new NoContactDetails],
            'summary' => ['nullable', 'string', 'max:3000', new NoContactDetails],
            'audio_url' => ['required', 'string', 'max:600'],
            'duration' => ['nullable', 'string', 'max:10', 'regex:/^\d{1,3}(:\d{1,2}){0,2}$/'],
            'season' => ['nullable', 'integer', 'min:1', 'max:99'],
            'number' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'published_at' => ['nullable', 'date'],
            'draft' => ['sometimes', 'boolean'],
        ], ['duration.regex' => __('Write the length like 24:10 (minutes and seconds), or just the minutes.')]);

        // A link that did not change is not looked at again; a new one must be a real audio link on an allowed host.
        if (! $existing || $existing->audio_url !== $data['audio_url']) {
            if ($problem = $this->podcasts->problemWithLink(trim($data['audio_url']))) {
                throw ValidationException::withMessages(['audio_url' => $problem]);
            }
        }

        return [
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'audio_url' => trim($data['audio_url']),
            'duration_seconds' => PodcastService::parseDuration($data['duration'] ?? null),
            'season' => $data['season'] ?? null,
            'number' => $data['number'] ?? null,
            'published_at' => isset($data['published_at']) ? Carbon::parse($data['published_at']) : ($existing?->published_at ?? now()),
            'status' => $request->boolean('draft') ? 'draft' : 'published',
        ];
    }
}
