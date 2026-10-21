<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Support\ChurchArtwork;
use App\Support\ContentArtwork;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The pictures and files people upload (church logos and banners, profile photos, sermon thumbnails…) live in storage/app/public and are meant to be
 * served from /storage by the web server, through the link that `php artisan storage:link` makes. On a shared server that link is easily missing,
 * and then every uploaded picture is a 404. This route answers /storage/… itself when the web server did not (when the link exists the web server
 * serves the file and this code is never reached).
 *
 * A file that is truly gone (the database says the church has a logo, the file is not on this server) is not left as a broken picture: a church
 * gets the FaithFellows logo on its brand colour, and an event, sermon or the like gets its generated cover.
 */
class StorageController extends Controller
{
    private const TYPES = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'pdf', 'mp3', 'm4a', 'aac', 'wav', 'mp4', 'webm', 'ogg'];

    public function show(string $path): BinaryFileResponse
    {
        $path = ltrim($path, '/');
        abort_if($path === '' || str_contains($path, '..') || str_contains($path, "\0") || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::TYPES, true), 404);

        $disk = Storage::disk('public');
        if (rescue(fn () => $disk->exists($path), false, false)) {
            return $this->send($disk->path($path), 86400);
        }
        abort_unless($stand = $this->standIn($path), 404);

        return $this->send($stand, 3600);   // short: the real file may be uploaded again
    }

    protected function send(string $file, int $seconds): BinaryFileResponse
    {
        // An uploaded SVG must never run scripts when it is opened on its own address.
        return response()->file($file, [
            'Cache-Control' => "public, max-age={$seconds}",
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    /** A drawn picture for a file that is missing, found from the media number at the start of its path (storage/{media id}/…), or null. */
    protected function standIn(string $path): ?string
    {
        if (! preg_match('#^(\d+)/#', $path, $match) || ! ($media = Media::find((int) $match[1])) || ! ($model = $media->model)) {
            return null;
        }
        if ($model instanceof Church) {
            $logo = $media->collection_name === 'logo';
            $file = storage_path('app/placeholders/church-'.$model->id.($logo ? '-logo.png' : '-banner.jpg'));
            if (! is_file($file)) {
                @mkdir(dirname($file), 0775, true);
                $hex = (string) ($model->brand_color ?: '#BC2649');
                $logo ? ChurchArtwork::logo($file, $model->name, $hex, $model->id % 5) : ChurchArtwork::banner($file, $hex, $model->id);
            }

            return is_file($file) ? $file : null;
        }
        $kind = array_search($model::class, ArtController::KINDS, true);

        return $kind !== false ? ContentArtwork::file($kind, $model) : null;
    }
}
