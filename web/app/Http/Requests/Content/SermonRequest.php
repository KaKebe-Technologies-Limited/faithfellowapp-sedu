<?php

namespace App\Http\Requests\Content;

use App\Rules\WithinStorageQuota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SermonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canInChurch($this->route('church'), 'content.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $church = $this->route('church');
        $maxKb = config('faithfellows.limits.max_upload_mb') * 1024;

        return [
            'title' => ['required', 'string', 'max:200'],
            'preacher' => ['nullable', 'string', 'max:120'],
            'scripture' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:50000'],
            'sermon_series_id' => ['nullable', Rule::exists('sermon_series', 'id')->where('church_id', $church->id)],
            'new_series' => ['nullable', 'string', 'max:160'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $church->id)],
            'language' => ['required', Rule::in(array_keys(config('faithfellows.languages')))],
            'preached_on' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'audio' => ['nullable', 'file', 'mimetypes:audio/mpeg,audio/mpeg3,audio/x-mpeg,audio/x-mpeg-3,audio/mp3,audio/mp4,audio/x-m4a,audio/aac,audio/ogg,audio/wav,audio/x-wav,audio/webm', 'max:'.(ff_audio_upload_mb($this->user()) * 1024), new WithinStorageQuota($church)],
            'audio_url' => ['nullable', 'url', 'max:500'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', "max:$maxKb", new WithinStorageQuota($church)],
            'video_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'visibility' => ['required', Rule::in(['public', 'members'])],
            'published_at' => ['nullable', 'date'],
            'transcript' => ['nullable', 'string', 'max:300000'],
            'remove_audio' => ['boolean'],
            'remove_video' => ['boolean'],
            'remove_notes' => ['boolean'],
        ];
    }
}
