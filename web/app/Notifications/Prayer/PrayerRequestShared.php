<?php

namespace App\Notifications\Prayer;

use App\Models\PrayerRequest;
use App\Notifications\ChurchNotification;
use Illuminate\Support\Str;

/**
 * To every church member once the pastor approves a request:
 * "Stand in prayer with Grace".
 */
class PrayerRequestShared extends ChurchNotification
{
    public string $category = 'prayer';

    public function __construct(public PrayerRequest $request)
    {
        $this->churchId = $request->church_id;
    }

    public function title(): string
    {
        return $this->request->is_anonymous
            ? __('🙏 Stand in prayer with a church member')
            : __('🙏 Stand in prayer with :name', ['name' => $this->request->user?->first_name ?? __('a church member')]);
    }

    public function body(): ?string
    {
        return ($this->request->title ? $this->request->title.' — ' : '').Str::limit($this->request->body, 140);
    }

    public function url(): string
    {
        return route('prayer.show', $this->request);
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
