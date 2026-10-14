<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Sermon;
use App\Services\EngagementTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SermonController extends Controller
{
    /**
     * All sermons from the churches I follow.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $churchIds = $user->memberChurchIds();

        $sermons = Sermon::query()
            ->with(['church.media', 'series', 'media'])
            ->forChurches($churchIds)
            ->published()
            ->search($request->query('q'))
            ->when($request->filled('church'), fn ($q) => $q->whereHas('church', fn ($c) => $c->where('slug', $request->query('church'))))
            ->when($request->filled('preacher'), fn ($q) => $q->where('preacher', $request->query('preacher')))
            ->when($request->filled('language'), fn ($q) => $q->where('language', $request->query('language')))
            ->when($request->query('format') === 'video', fn ($q) => $q->where(fn ($v) => $v->whereNotNull('video_url')->orWhereHas('media', fn ($m) => $m->where('collection_name', 'video'))))
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('member.sermons.index', [
            'sermons' => $sermons,
            'churches' => Church::whereIn('id', $churchIds)->orderBy('name')->pluck('name', 'slug'),
            'preachers' => Sermon::forChurches($churchIds)->published()->whereNotNull('preacher')->distinct()->orderBy('preacher')->pluck('preacher'),
        ]);
    }

    /**
     * Sermon player — public sermons are viewable by visitors too.
     */
    public function show(Request $request, Sermon $sermon, EngagementTracker $tracker): View
    {
        $this->authorize('view', $sermon);

        $sermon->load(['church.media', 'series', 'media', 'transcripts', 'playlists.sermons']);
        $tracker->view($sermon);

        $related = Sermon::query()->with(['church.media', 'media'])
            ->where('church_id', $sermon->church_id)->whereKeyNot($sermon->id)
            ->published()->visibleTo($request->user()?->memberChurchIds() ?? [])
            ->when($sermon->sermon_series_id, fn ($q) => $q->orderByRaw('sermon_series_id = ? desc', [$sermon->sermon_series_id]))
            ->latest('published_at')->take(4)->get();

        return view('member.sermons.show', compact('sermon', 'related'));
    }

    public function track(Request $request, Sermon $sermon, EngagementTracker $tracker): JsonResponse
    {
        $this->authorize('view', $sermon);
        $data = $request->validate(['seconds' => ['required', 'integer', 'min:0', 'max:43200'], 'action' => ['nullable', 'in:listen,watch']]);
        $tracker->listen($sermon, $data['seconds'], $data['action'] ?? 'listen');

        return response()->json(['ok' => true]);
    }

    public function download(Sermon $sermon, EngagementTracker $tracker): Response|RedirectResponse
    {
        $this->authorize('view', $sermon);
        $tracker->download($sermon);

        $media = $sermon->getFirstMedia('audio');
        if ($media) {
            return response()->download($media->getPath(), str($sermon->title)->slug().'.'.pathinfo($media->file_name, PATHINFO_EXTENSION));
        }

        abort_unless($sermon->audio_url, 404);

        return redirect()->away($sermon->audio_url);
    }
}
