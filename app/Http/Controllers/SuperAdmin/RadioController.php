<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\RadioTrack;
use App\Services\RadioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The gospel radio, from the system end: the super admin adds the songs, arranges their order, switches songs on
 * and off, and names the station. The app plays them round the clock, in this order, in a loop.
 */
class RadioController extends Controller
{
    public function __construct(protected RadioService $radio) {}

    public function index(): View
    {
        $tracks = RadioTrack::orderBy('position')->orderBy('id')->get();

        return view('super-admin.radio.index', [
            'tracks' => $tracks,
            'now' => $this->radio->nowPlaying(),
            'name' => $this->radio->name(),
            'enabled' => $this->radio->enabled(),
            'loop' => $this->radio->loopSeconds(),
            'limit' => ini_get('upload_max_filesize'),
        ]);
    }

    /** One song per request (the page sends the chosen files one after another); answers JSON for the page, or goes back. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['track' => ['required', 'file', 'max:61440'], 'title' => ['nullable', 'string', 'max:160'], 'artist' => ['nullable', 'string', 'max:160']]);
        $file = $request->file('track');

        try {
            $track = $this->radio->addFile($file->getRealPath(), $file->getClientOriginalName(), $request->user(), $request->input('title'), $request->input('artist'));
        } catch (\InvalidArgumentException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['track' => $e->getMessage()]);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'title' => $track->title])
            : back()->with('success', __('“:title” was added to the radio.', ['title' => $track->title]));
    }

    public function update(Request $request, RadioTrack $track): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'artist' => ['sometimes', 'nullable', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $track->update($data);

        return back()->with('success', __('Saved.'));
    }

    public function move(Request $request, RadioTrack $track): RedirectResponse
    {
        $this->radio->move($track, $request->validate(['direction' => ['required', 'in:up,down']])['direction']);

        return back();
    }

    public function destroy(RadioTrack $track): RedirectResponse
    {
        $this->radio->delete($track);

        return back()->with('success', __('The song was removed from the radio.'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'enabled' => ['boolean']]);
        $this->radio->saveSettings($data['name'], $request->boolean('enabled'));

        return back()->with('success', __('Radio settings saved.'));
    }
}
