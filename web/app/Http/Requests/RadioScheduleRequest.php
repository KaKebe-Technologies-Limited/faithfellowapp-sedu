<?php

namespace App\Http\Requests;

use App\Models\RadioSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** "Put this song on air at this time": which track, when (a time still ahead, in the app's time zone) and whether it repeats. */
class RadioScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'track' => ['required', 'integer'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'],
            'repeat' => ['required', Rule::in(array_keys(RadioSchedule::REPEATS))],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'starts_at.after' => __('Pick a time that is still ahead.'),
            'starts_at.date_format' => __('Pick a date and a time.'),
            'track.required' => __('Choose the song to put on air.'),
        ];
    }

    public function startsAt(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d\TH:i', (string) $this->validated('starts_at'), config('app.timezone'))->seconds(0);
    }
}
