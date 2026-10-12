<?php

namespace App\Notifications\Live;

use App\Models\LiveStream;
use App\Notifications\ChurchNotification;

class LiveStreamStarted extends ChurchNotification
{
    public string $category = 'live';

    public function __construct(public LiveStream $stream)
    {
        $this->churchId = $stream->church_id;
    }

    public function title(): string
    {
        return __('🔴 :church is live now', ['church' => $this->stream->church?->name ?? '']);
    }

    public function body(): ?string
    {
        return $this->stream->title.' — '.__('tap to join (audio-only available to save data).');
    }

    public function url(): string
    {
        return route('live.show', $this->stream);
    }

    public function icon(): string
    {
        return 'bi-broadcast';
    }

    public function churchName(): ?string
    {
        return $this->stream->church?->name;
    }
}
