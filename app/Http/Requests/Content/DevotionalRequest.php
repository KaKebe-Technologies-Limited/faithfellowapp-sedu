<?php

namespace App\Http\Requests\Content;

use App\Rules\WithinStorageQuota;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DevotionalRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:200'],
            'scripture' => ['nullable', 'string', 'max:160'],
            'scripture_text' => ['nullable', 'string', 'max:2000'],
            'body' => ['required', 'string', 'max:30000'],
            'prayer' => ['nullable', 'string', 'max:1000'],
            'publish_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'visibility' => ['required', Rule::in(['public', 'members'])],
            'audio' => ['nullable', 'file', 'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/aac,audio/ogg,audio/webm', 'max:51200', new WithinStorageQuota($this->route('church'))],
            'audio_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
