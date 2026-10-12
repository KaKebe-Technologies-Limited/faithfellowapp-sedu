<?php

namespace App\Http\Requests\Giving;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canInChurch($this->route('church'), 'campaigns.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $church = $this->route('church');

        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(array_keys(config('faithfellows.campaign_types')))],
            'short_description' => ['nullable', 'string', 'max:300'],
            'story' => ['nullable', 'string', 'max:60000'],
            'goal_amount' => ['required', 'numeric', 'min:1000', 'max:100000000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['draft', 'active', 'completed', 'cancelled'])],
            'visibility' => ['required', Rule::in(['public', 'members'])],
            'show_leaderboard' => ['boolean'],
            'allow_anonymous' => ['boolean'],
            'is_featured' => ['boolean'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('church_id', $church->id)],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'gallery' => ['nullable', 'array', 'max:10'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'teams' => ['nullable', 'array', 'max:12'],
            'teams.*.id' => ['nullable', 'integer'],
            'teams.*.name' => ['nullable', 'string', 'max:120'],
            'teams.*.goal_amount' => ['nullable', 'numeric', 'min:0'],
            'teams.*.color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
