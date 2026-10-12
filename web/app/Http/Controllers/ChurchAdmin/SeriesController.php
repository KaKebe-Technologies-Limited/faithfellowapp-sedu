<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Playlist;
use App\Models\SermonSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Sermon series and playlists (grouping sermons by theme).
 */
class SeriesController extends Controller
{
    public function store(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ]);
        $series = $church->series()->create(collect($data)->except('cover')->all());
        if ($request->hasFile('cover')) {
            $series->addMediaFromRequest('cover')->toMediaCollection('cover');
        }

        return back()->with('success', __('Series created.'));
    }

    public function destroy(Church $church, SermonSeries $series): RedirectResponse
    {
        $series->delete();

        return back()->with('success', __('Series deleted. Its sermons were kept.'));
    }

    public function storePlaylist(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['boolean'],
            'sermon_ids' => ['nullable', 'array'],
            'sermon_ids.*' => [Rule::exists('sermons', 'id')->where('church_id', $church->id)],
        ]);
        $playlist = $church->playlists()->create(collect($data)->except('sermon_ids')->all());
        $playlist->sermons()->sync(collect($data['sermon_ids'] ?? [])->values()->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());

        return back()->with('success', __('Playlist created.'));
    }

    public function updatePlaylist(Request $request, Church $church, Playlist $playlist): RedirectResponse
    {
        $data = $request->validate([
            'sermon_ids' => ['nullable', 'array'],
            'sermon_ids.*' => [Rule::exists('sermons', 'id')->where('church_id', $church->id)],
        ]);
        $playlist->sermons()->sync(collect($data['sermon_ids'] ?? [])->values()->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());

        return back()->with('success', __('Playlist updated.'));
    }

    public function destroyPlaylist(Church $church, Playlist $playlist): RedirectResponse
    {
        $playlist->delete();

        return back()->with('success', __('Playlist deleted.'));
    }
}
