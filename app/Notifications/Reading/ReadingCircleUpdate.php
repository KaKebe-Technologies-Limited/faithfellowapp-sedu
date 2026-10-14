<?php

namespace App\Notifications\Reading;

use App\Models\ReadingCircle;
use App\Notifications\ChurchNotification;

/**
 * Reading-circle alerts: it's reading time, a partner finished, someone joined.
 *
 * @param  'reminder'|'finished'|'all_done'|'joined'  $kind
 */
class ReadingCircleUpdate extends ChurchNotification
{
    public string $category = 'reading';

    public function __construct(public ReadingCircle $circle, public string $kind, public ?string $partner = null, public ?int $day = null, public ?string $passages = null)
    {
        $this->churchId = $circle->church_id;
        // Reading time is the whole point of the reminder — don't hold it for quiet hours.
        $this->urgent = $kind === 'reminder';
    }

    public function title(): string
    {
        return match ($this->kind) {
            'reminder' => __('📖 Reading time with :circle', ['circle' => $this->circle->name]),
            'finished' => __(':name finished Day :day', ['name' => $this->partner, 'day' => $this->day]),
            'all_done' => __('🎉 Everyone in :circle finished Day :day', ['circle' => $this->circle->name, 'day' => $this->day]),
            default => __(':name joined :circle', ['name' => $this->partner, 'circle' => $this->circle->name]),
        };
    }

    public function body(): ?string
    {
        return match ($this->kind) {
            'reminder' => __('Day :day: :passages. Read together, then share what stood out.', ['day' => $this->day, 'passages' => $this->passages]),
            'finished' => __('Their summary is waiting for you. Day :day: :passages.', ['day' => $this->day, 'passages' => $this->passages]),
            'all_done' => __('Read each other’s summaries and encourage one another.'),
            default => __('You are now :count reading together.', ['count' => $this->circle->memberships()->count()]),
        };
    }

    public function url(): string
    {
        return route('reading-circles.show', [$this->circle, 'day' => $this->day]);
    }

    public function icon(): string
    {
        return match ($this->kind) {
            'reminder' => 'bi-alarm',
            'finished', 'all_done' => 'bi-check2-all',
            default => 'bi-people',
        };
    }

    public function churchName(): ?string
    {
        return $this->circle->name;
    }
}
