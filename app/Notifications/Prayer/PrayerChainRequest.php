<?php

namespace App\Notifications\Prayer;

use App\Models\PrayerRequest;
use App\Notifications\ChurchNotification;
use Illuminate\Support\Str;

class PrayerChainRequest extends ChurchNotification
{
    public string $category = 'prayer';

    public function __construct(public PrayerRequest $request)
    {
        $this->churchId = $request->church_id;
    }

    public function title(): string
    {
        return __('🙏 You’re next in a prayer chain');
    }

    public function body(): ?string
    {
        return Str::limit($this->request->body, 160).' — '.__('Tap “I prayed” to pass it on.');
    }

    public function url(): string
    {
        return route('prayer.show', $this->request);
    }

    public function icon(): string
    {
        return 'bi-link';
    }

    public function churchName(): ?string
    {
        return $this->request->church?->name;
    }
}
