<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class ReadingPlanRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['boolean'],
            // One line per day: "Genesis 1-3 | Optional title | Optional reflection"
            'schedule' => ['required', 'string', 'max:100000'],
        ];
    }

    /**
     * Parse the textarea schedule into plan entries.
     *
     * @return array<int, array{day: int, title: ?string, passages: string, reflection: ?string}>
     */
    public function entries(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->input('schedule')))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->map(function (string $line, int $i) {
                $parts = array_map('trim', explode('|', $line));

                return [
                    'day' => $i + 1,
                    'passages' => $parts[0],
                    'title' => $parts[1] ?? null ?: null,
                    'reflection' => $parts[2] ?? null ?: null,
                ];
            })
            ->take(731)
            ->all();
    }
}
