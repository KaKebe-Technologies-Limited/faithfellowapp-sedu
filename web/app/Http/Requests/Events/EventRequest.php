<?php

namespace App\Http\Requests\Events;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canInChurch($this->route('church'), 'events.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $church = $this->route('church');

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:30000'],
            'location_name' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_online' => ['boolean'],
            'online_url' => ['nullable', 'url', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'rsvp_enabled' => ['boolean'],
            'is_paid' => ['boolean'],
            'is_cross_branch' => ['boolean'],
            'qa_enabled' => ['boolean'],
            'status' => ['required', Rule::in(['draft', 'published', 'cancelled'])],
            'visibility' => ['required', Rule::in(['public', 'members'])],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $church->id)],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'recording_url' => ['nullable', 'url', 'max:500'],
            'agenda' => ['nullable', 'array', 'max:40'],
            'agenda.*.time' => ['nullable', 'string', 'max:20'],
            'agenda.*.title' => ['nullable', 'string', 'max:160'],
            'agenda.*.speaker' => ['nullable', 'string', 'max:120'],
            'speakers' => ['nullable', 'array', 'max:20'],
            'speakers.*.name' => ['nullable', 'string', 'max:120'],
            'speakers.*.role' => ['nullable', 'string', 'max:120'],
            'speakers.*.bio' => ['nullable', 'string', 'max:500'],
            'tickets' => ['nullable', 'array', 'max:10'],
            'tickets.*.id' => ['nullable', 'integer'],
            'tickets.*.name' => ['required_with:tickets.*.price', 'nullable', 'string', 'max:120'],
            'tickets.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'tickets.*.quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
