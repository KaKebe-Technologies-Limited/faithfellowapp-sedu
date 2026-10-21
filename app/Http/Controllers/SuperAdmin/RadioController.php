<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use App\Services\RadioService;
use App\Services\RadioUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The radio channels, from the system end. The super admin fills the platform's two channels (the Gospel radio with songs and the Podcast radio
 * with teaching and podcasts), arranges their order, switches tracks on and off and names the channel. Each plays round the clock, in a loop.
 * Church channels are filled by the churches themselves; the super admin can only switch one off here.
 */
class RadioController extends Controller
{
    public function __construct(protected RadioService $radio, protected RadioUploads $uploads) {}

    /** The platform channel the page is about: ?station=podcast, otherwise the Gospel radio. */
    protected function station(Request $request): RadioStation
    {
        return RadioStation::platform((string) $request->input('station', RadioStation::GOSPEL));
    }

    public function index(Request $request): View
    {
        $station = $this->station($request);
        $tracks = RadioTrack::ofStation($station)->orderBy('position')->orderBy('id')->get();

        return view('super-admin.radio.index', [
            'station' => $station,
            'platformStations' => RadioStation::ready() ? [RadioStation::gospel(), RadioStation::podcast()] : [RadioStation::gospel()],
            'tracks' => $tracks,
            'now' => $this->radio->nowPlaying(null, 4, 5, $station),
            'name' => $this->radio->name($station),
            'enabled' => $this->radio->enabled($station),
            'loopSeconds' => $this->radio->loopSeconds($station),
            'limitMb' => ff_audio_upload_mb(request()->user()),
            'churchStations' => RadioStation::ready() ? RadioStation::whereNotNull('church_id')->with('church')->withCount('tracks')->orderBy('name')->get() : collect(),
        ]);
    }

    /**
     * One song per request, for a song small enough to arrive in one piece (the page uses {@see chunk()} instead, which has no
     * limit from the server's upload size); answers JSON for the page, or goes back.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate(['track' => ['required', 'file', 'max:'.(ff_audio_upload_mb($request->user()) * 1024)], 'title' => ['nullable', 'string', 'max:160'], 'artist' => ['nullable', 'string', 'max:160']]);
        $file = $request->file('track');
        $station = $this->station($request);

        try {
            $track = $this->radio->addFile($file->getRealPath(), $file->getClientOriginalName(), $request->user(), $request->input('title'), $request->input('artist'), $station);
        } catch (\InvalidArgumentException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['track' => $e->getMessage()]);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'title' => $track->title])
            : back()->with('success', __('“:title” was added to the radio.', ['title' => $track->title]));
    }

    /** One piece (about 4 MB) of a big file; the last piece puts it together and adds it to the channel. Up to the admin's limit (400 MB). */
    public function chunk(Request $request): JsonResponse
    {
        $station = $this->station($request);
        $limitMb = ff_audio_upload_mb($request->user());

        return $this->uploads->receive($request, $station, fn (int $bytes) => $bytes > $limitMb * 1024 * 1024
            ? __('A :noun can be up to :mb MB.', ['noun' => __($station->noun()), 'mb' => $limitMb])
            : null);
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

    /** Puts this song on air right now; the live radio moves to it for everyone who is listening. */
    public function play(RadioTrack $track): RedirectResponse
    {
        $this->radio->playNow($track);

        return back()->with('success', __('“:title” is on air now. Listeners move to it within a few seconds.', ['title' => $track->title]));
    }

    public function destroy(RadioTrack $track): RedirectResponse
    {
        $this->radio->delete($track);

        return back()->with('success', __('Removed from the radio.'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'enabled' => ['boolean']]);
        $this->radio->saveSettings($data['name'], $request->boolean('enabled'), $this->station($request));

        return back()->with('success', __('Radio settings saved.'));
    }

    /** Switches a church's channel off for everyone (or back on). The church cannot undo it. */
    public function block(RadioStation $station): RedirectResponse
    {
        abort_if($station->isPlatform(), 404);
        $station->update(['is_blocked' => ! $station->is_blocked]);

        return back()->with('success', $station->is_blocked ? __(':name was switched off.', ['name' => $station->name]) : __(':name was switched back on.', ['name' => $station->name]));
    }
}
