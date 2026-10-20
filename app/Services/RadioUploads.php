<?php

namespace App\Services;

use App\Models\RadioStation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Receives a big audio file in pieces (about 4 MB each) for a radio channel, from the platform admin's page and from a church's page. A big file
 * cannot travel in one request on shared hosting, so the page cuts it up and sends the pieces in order; the last piece puts the file together
 * and adds it to the channel. Unfinished uploads are removed after a day.
 */
class RadioUploads
{
    public function __construct(protected RadioService $radio) {}

    /**
     * @param  callable(int): ?string  $refuse  gets the bytes received so far and returns the reason this upload is too big, or null
     */
    public function receive(Request $request, RadioStation $station, callable $refuse): JsonResponse
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
        abort_if($data['index'] >= $data['total'], 422, __('The pieces do not add up.'));

        $disk = Storage::disk('local');
        $this->sweepOldUploads($disk);
        $dir = 'radio-uploads/'.$request->user()->id.'-'.$data['upload_id'];
        $request->file('chunk')->storeAs($dir, $data['index'].'.part', 'local');

        $bytes = (int) collect($disk->files($dir))->sum(fn ($file) => $disk->size($file));
        if ($reason = $refuse($bytes)) {
            $disk->deleteDirectory($dir);

            return response()->json(['message' => $reason], 422);
        }
        if ($data['index'] < $data['total'] - 1) {
            return response()->json(['ok' => true, 'received' => $data['index'] + 1]);
        }

        // The last piece: put the file together in order, then add it like any other file.
        for ($i = 0; $i < $data['total']; $i++) {
            if (! $disk->exists("{$dir}/{$i}.part")) {
                $disk->deleteDirectory($dir);

                return response()->json(['message' => __('A piece of the file did not arrive. Please upload it again.')], 422);
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
            $track = $this->radio->addFile($whole, $data['name'], $request->user(), $data['title'] ?? null, $data['artist'] ?? null, $station);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            @unlink($whole);
            $disk->deleteDirectory($dir);
        }

        return response()->json(['ok' => true, 'title' => $track->title]);
    }

    /** Pieces that were never finished (a closed page, a lost connection) are removed after a day. */
    protected function sweepOldUploads($disk): void
    {
        foreach ($disk->directories('radio-uploads') as $dir) {
            if ($disk->lastModified($dir) < now()->subDay()->getTimestamp()) {
                $disk->deleteDirectory($dir);
            }
        }
    }
}
