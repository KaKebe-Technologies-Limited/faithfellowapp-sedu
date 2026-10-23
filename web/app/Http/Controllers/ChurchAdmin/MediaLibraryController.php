<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Media;
use App\Rules\WithinStorageQuota;
use App\Support\StorageQuota;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

/**
 * Per-church media library: every file the church has uploaded, with the
 * storage quota for its plan.
 */
class MediaLibraryController extends Controller
{
    public function index(Request $request, Church $church): View
    {
        $type = $request->query('type');

        $media = Media::where('church_id', $church->id)
            ->when($type === 'image', fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($type === 'audio', fn ($q) => $q->where('mime_type', 'like', 'audio/%'))
            ->when($type === 'video', fn ($q) => $q->where('mime_type', 'like', 'video/%'))
            ->when($type === 'document', fn ($q) => $q->where('mime_type', 'not like', 'image/%')->where('mime_type', 'not like', 'audio/%')->where('mime_type', 'not like', 'video/%'))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('church-admin.media.index', [
            'church' => $church,
            'media' => $media,
            'usedMb' => StorageQuota::usedMb($church),
            'limitMb' => StorageQuota::limitMb($church),
            'percent' => StorageQuota::percentUsed($church),
        ]);
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20', new WithinStorageQuota($church)],
            'files.*' => ['file', 'max:'.(config('faithfellows.limits.max_upload_mb') * 1024),
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,audio/mpeg,audio/mpeg3,audio/x-mpeg,audio/x-mpeg-3,audio/mp4,audio/x-m4a,audio/ogg,video/mp4,video/webm,application/pdf',
                // Songs and recordings have their own, smaller limit for church staff (bigger for the platform admin).
                function (string $attribute, mixed $value, Closure $fail) use ($request) {
                    $mb = ff_audio_upload_mb($request->user());
                    if ($value instanceof UploadedFile && str_starts_with((string) $value->getMimeType(), 'audio/') && $value->getSize() > $mb * 1024 * 1024) {
                        $fail(__('An audio file can be up to :mb MB.', ['mb' => $mb]));
                    }
                }],
        ]);

        foreach ($request->file('files') as $file) {
            $church->addMedia($file)->toMediaCollection('library');
        }

        return back()->with('success', trans_choice(':count file uploaded.|:count files uploaded.', count($request->file('files'))));
    }

    public function destroy(Church $church, int $mediaId): RedirectResponse
    {
        // Tenant check: only this church's media can be deleted.
        $media = Media::where('church_id', $church->id)->findOrFail($mediaId);
        $media->delete();

        return back()->with('success', __('File deleted.'));
    }
}
