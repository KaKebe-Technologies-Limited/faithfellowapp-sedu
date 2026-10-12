<?php

namespace App\Notifications\Prayer;

use App\Models\PrayerRequest;
use App\Notifications\ChurchNotification;
use Illuminate\Support\Str;

/**
 * To everyone who prayed when the request is marked answered.
 */
class PrayerAnswered extends ChurchNotification
{
    public string $category = 'prayer';

    public function __construct(public PrayerRequest $request)
    {
        $this->churchId = $request->church_id;
    }

    public function title(): string
    {
        return __('🎉 A prayer you prayed was answered!');
    }

    public function body(): ?string
    {
        return $this->request->answer_note ? Str::limit($this->request->answer_note, 200) : Str::limit($this->request->body, 140);
    }

    public function url(): string
    {
        return route('prayer.show', $this->request);
    }

    public function icon(): string
    {
        return 'bi-stars';
    }

    public function churchName(): ?string
    {
        return $this->request->church?->name;
    }
}
