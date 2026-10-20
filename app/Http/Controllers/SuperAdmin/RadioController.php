<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\RadioTrack;
use App\Services\RadioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'limitMb' => ff_audio_upload_mb(request()->user()),
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

    /**
     * One piece (about 4 MB) of a song. A big song cannot travel in one request on shared hosting, so the page cuts it into pieces and
     * sends them in order; the last piece puts the song together and adds it to the radio. The total is held to the admin's limit
     * (400 MB). Unfinished uploads are swept away after a day.
     */
    public function chunk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,64}$/'],
            'index' => ['required', 'integer', 'min:0', 'max:999'],
            'total' => ['required', 'integer', 'min:1', 'max:1000'],
            'name' => ['required', 'string', 'max:255'],
            'chunk' => ['required', 'file', 'max:8192'],
            'title' => ['nullable', 'string', 'max:160'],
            'artist' => ['nullable', 'string', 'max:160'],
        ]);
        abort_if($data['index'] >= $data['total'], 422, __('The song pieces do not add up.'));

        $disk = Storage::disk('local');
        $this->sweepOldUploads($disk);
        $dir = 'radio-uploads/'.$request->user()->id.'-'.$data['upload_id'];
        $request->file('chunk')->storeAs($dir, $data['index'].'.part', 'local');

        $limitMb = ff_audio_upload_mb($request->user());
        $bytes = collect($disk->files($dir))->sum(fn ($file) => $disk->size($file));
        if ($bytes > $limitMb * 1024 * 1024) {
            $disk->deleteDirectory($dir);

            return response()->json(['message' => __('A song can be up to :mb MB.', ['mb' => $limitMb])], 422);
        }
        if ($data['index'] < $data['total'] - 1) {
            return response()->json(['ok' => true, 'received' => $data['index'] + 1]);
        }

        // The last piece: put the song together in order, then add it like any other file.
        for ($i = 0; $i < $data['total']; $i++) {
            if (! $disk->exists("{$dir}/{$i}.part")) {
                $disk->deleteDirectory($dir);

                return response()->json(['message' => __('A piece of the song did not arrive. Please upload it again.')], 422);
            }
        }
        $whole = tempnam(sys_get_temp_dir(), 'song');
        try {
            $out = fopen($whole, 'wb');
            for ($i = 0; $i < $data['total']; $i++) {
                $in = $disk->readStream("{$dir}/{$i}.part");
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
            fclose($out);
            $track = $this->radio->addFile($whole, $data['name'], $request->user(), $data['title'] ?? null, $data['artist'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            @unlink($whole);
            $disk->deleteDirectory($dir);
        }

        return response()->json(['ok' => true, 'title' => $track->title]);
    }

    /** Pieces of songs that were never finished (a closed page, a lost connection) are removed after a day. */
    protected function sweepOldUploads($disk): void
    {
        foreach ($disk->directories('radio-uploads') as $dir) {
            if ($disk->lastModified($dir) < now()->subDay()->getTimestamp()) {
                $disk->deleteDirectory($dir);
            }
        }
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
