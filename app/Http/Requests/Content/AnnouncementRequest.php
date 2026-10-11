<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
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

        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['required', Rule::in(['public', 'members', 'staff', 'branch', 'group'])],
            'branch_id' => ['nullable', 'required_if:audience,branch', Rule::exists('branches', 'id')->where('church_id', $church->id)],
            'small_group_id' => Schema::hasTable('small_groups')
                ? ['nullable', 'required_if:audience,group', Rule::exists('small_groups', 'id')->where('church_id', $church->id)]
                : ['prohibited_if:audience,group'],
            'priority' => ['required', Rule::in(['normal', 'urgent'])],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(['mail', 'sms', 'whatsapp'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
