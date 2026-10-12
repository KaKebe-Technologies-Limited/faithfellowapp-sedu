<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\SermonRequest;
use App\Jobs\TranscribeSermon;
use App\Models\Church;
use App\Models\Sermon;
use App\Services\SermonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SermonController extends Controller
{
    public function __construct(protected SermonService $sermons) {}

    public function index(Request $request, Church $church): View
    {
        $sermons = $church->sermons()
            ->with(['series', 'media'])
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('series'), fn ($q) => $q->where('sermon_series_id', $request->query('series')))
            ->latest('published_at')->latest('id')
            ->paginate(20)->withQueryString();

        return view('church-admin.sermons.index', [
            'church' => $church,
            'sermons' => $sermons,
            'series' => $church->series()->withCount('sermons')->latest()->get(),
            'playlists' => $church->playlists()->withCount('sermons')->latest()->get(),
        ]);
    }

    public function create(Church $church): View
    {
        return $this->form($church, new Sermon(['language' => 'en', 'status' => 'published', 'visibility' => 'public']));
    }

    public function store(SermonRequest $request, Church $church): RedirectResponse
    {
        $sermon = $this->sermons->save($church, $request, null, $request->user());

        return redirect()->route('church-admin.sermons.index', $church)->with('success', match ($sermon->status) {
            'draft' => __('Sermon saved as draft.'),
            'scheduled' => __('Sermon scheduled for :date.', ['date' => $sermon->published_at->format('j M, H:i')]),
            default => __('Sermon published and subscribers notified.'),
        });
    }

    public function edit(Church $church, Sermon $sermon): View
    {
        return $this->form($church, $sermon->load(['media', 'transcripts']));
    }

    public function update(SermonRequest $request, Church $church, Sermon $sermon): RedirectResponse
    {
        $this->sermons->save($church, $request, $sermon);

        return redirect()->route('church-admin.sermons.index', $church)->with('success', __('Sermon updated.'));
    }

    public function destroy(Church $church, Sermon $sermon): RedirectResponse
    {
        $this->authorize('delete', $sermon);
        $sermon->delete();

        return back()->with('success', __('Sermon deleted.'));
    }

    /**
     * Sermon AI (Phase 2 stub): queue a transcription request.
     */
    public function transcribe(Church $church, Sermon $sermon): RedirectResponse
    {
        $this->authorize('update', $sermon);
        $sermon->transcripts()->updateOrCreate(['language' => $sermon->language], [
            'church_id' => $church->id, 'source' => 'ai', 'status' => 'queued',
        ]);
        TranscribeSermon::dispatch($sermon->id);

        return back()->with('info', __('Transcription requested. You will see it here when it is ready.'));
    }

    protected function form(Church $church, Sermon $sermon): View
    {
        return view('church-admin.sermons.form', [
            'church' => $church,
            'sermon' => $sermon,
            'series' => $church->series()->orderBy('title')->pluck('title', 'id'),
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
