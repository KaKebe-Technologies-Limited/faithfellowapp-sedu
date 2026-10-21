<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\RadioStation;
use App\Models\RadioTrack;
use App\Services\RadioService;
use App\Services\RadioUploads;
use App\Support\StorageQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A church's own radio channel. The church adds teaching, sermons and podcasts; the app plays them round the clock, in a loop, for the members
 * who follow the church. Every church gets the channel when it first opens this page. Audio counts against the church's storage, and a file can be
 * up to 150 MB (the platform admin's own channels allow more).
 */
class RadioController extends Controller
{
    public function __construct(protected RadioService $radio, protected RadioUploads $uploads) {}

    /** This church's channel, made on first use. The database update that adds channels must have been applied. */
    protected function station(Church $church): RadioStation
    {
        abort_unless(RadioStation::ready(), 503, __('Radio channels are being set up. Please try again in a few minutes.'));

        return RadioStation::forChurch($church);
    }

    public function index(Church $church): View
    {
        $station = $this->station($church);

        return view('church-admin.radio.index', [
            'church' => $church,
            'station' => $station,
            'tracks' => RadioTrack::ofStation($station)->orderBy('position')->orderBy('id')->get(),
            'now' => $this->radio->nowPlaying(null, 4, 5, $station),
            'name' => $this->radio->name($station),
            'enabled' => $this->radio->enabled($station),
            'loopSeconds' => $this->radio->loopSeconds($station),
            'limitMb' => ff_audio_upload_mb(request()->user()),
            'usedMb' => StorageQuota::usedMb($church),
            'storageLimitMb' => StorageQuota::limitMb($church),
            'percent' => StorageQuota::percentUsed($church),
        ]);
    }

    /** One piece (about 4 MB) of a big file; the last piece puts it together and adds it to the church's channel. */
    public function chunk(Request $request, Church $church): JsonResponse
    {
        $station = $this->station($church);
        abort_if($station->is_blocked, 403, __('This channel has been switched off by FaithFellows. Please contact support.'));
        $limitMb = ff_audio_upload_mb($request->user());

        return $this->uploads->receive($request, $station, function (int $bytes) use ($limitMb, $church, $station) {
            if ($bytes > $limitMb * 1024 * 1024) {
                return __('An :noun can be up to :mb MB.', ['noun' => __($station->noun()), 'mb' => $limitMb]);
            }
            if (! StorageQuota::canStore($church, $bytes)) {
                return __('This would exceed your church’s storage (:used of :limit MB used). Remove old media or upgrade your plan.', ['used' => StorageQuota::usedMb($church), 'limit' => StorageQuota::limitMb($church)]);
            }

            return null;
        });
    }

    public function settings(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'enabled' => ['boolean']]);
        $this->radio->saveSettings($data['name'], $request->boolean('enabled'), $this->station($church));

        return back()->with('success', __('Channel settings saved.'));
    }

    public function update(Request $request, Church $church, int $trackId): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'artist' => ['sometimes', 'nullable', 'string', 'max:160'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $this->track($church, $trackId)->update($data);

        return back()->with('success', __('Saved.'));
    }

    public function move(Request $request, Church $church, int $trackId): RedirectResponse
    {
        $this->radio->move($this->track($church, $trackId), $request->validate(['direction' => ['required', 'in:up,down']])['direction']);

        return back();
    }

    /** Puts this episode on air right now; the church's channel moves to it for everyone who is listening. */
    public function play(Church $church, int $trackId): RedirectResponse
    {
        $track = $this->track($church, $trackId);
        $this->radio->playNow($track);

        return back()->with('success', __('“:title” is on air now. Listeners move to it within a few seconds.', ['title' => $track->title]));
    }

    public function destroy(Church $church, int $trackId): RedirectResponse
    {
        $this->radio->delete($this->track($church, $trackId));

        return back()->with('success', __('Removed from your channel.'));
    }

    /** Only a track of this church's own channel can be changed through its URLs. */
    protected function track(Church $church, int $trackId): RadioTrack
    {
        return RadioTrack::where('station_id', $this->station($church)->id)->findOrFail($trackId);
    }
}
