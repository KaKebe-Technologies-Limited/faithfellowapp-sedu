<?php

namespace App\Notifications\Prayer;

use App\Models\PrayerRequest;
use App\Notifications\ChurchNotification;
use Illuminate\Support\Str;

/**
 * To the person who asked: approved & shared, kept private, or the pastor replied.
 *
 * @param  'approved'|'declined'|'responded'  $kind
 */
class PrayerRequestReviewed extends ChurchNotification
{
    public string $category = 'prayer';

    public function __construct(public PrayerRequest $request, public string $kind)
    {
        $this->churchId = $request->church_id;
        // The author always hears back, whatever their prayer alert settings.
        $this->forceChannels = ['database'];
    }

    public function title(): string
    {
        return match ($this->kind) {
            'approved' => __('Your prayer request was shared with the church'),
            'responded' => __('Your pastor replied to your prayer request'),
            default => __('Your pastor will pray with you privately'),
        };
    }

    public function body(): ?string
    {
        return match ($this->kind) {
            'approved' => __('Your church family is standing in prayer with you.'),
            'responded' => Str::limit((string) $this->request->pastor_response, 160),
            default => __('Your request was not posted on the wall, but the pastoral team has received it.'),
        };
    }

    public function url(): string
    {
        return route('prayer.show', $this->request);
    }

    public function icon(): string
    {
        return $this->kind === 'responded' ? 'bi-chat-quote' : 'bi-chat-heart';
    }

    public function churchName(): ?string
    {
        return $this->request->church?->name;
    }
}
