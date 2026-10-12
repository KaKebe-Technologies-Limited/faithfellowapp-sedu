<?php

namespace App\Notifications\Prayer;

use App\Models\PrayerRequest;
use App\Notifications\ChurchNotification;
use Illuminate\Support\Str;

/**
 * To pastors / prayer team when a member submits a request.
 */
class PrayerRequestSubmitted extends ChurchNotification
{
    public string $category = 'prayer';

    public function __construct(public PrayerRequest $request)
    {
        $this->churchId = $request->church_id;
        $this->forceChannels = ['database'];
    }

    public function title(): string
    {
        return $this->request->visibility === 'pastor'
            ? __('🔒 Private prayer request from :name', ['name' => $this->request->author_name])
            : __('New prayer request from :name', ['name' => $this->request->author_name]);
    }

    public function body(): ?string
    {
        return Str::limit($this->request->body, 160);
    }

    public function url(): string
    {
        return route('church-admin.prayer.index', $this->request->church);
    }

    public function icon(): string
    {
        return 'bi-chat-heart';
    }

    public function churchName(): ?string
    {
        return $this->request->church?->name;
    }
}
