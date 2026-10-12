<?php

namespace App\Notifications\Prayer;

use App\Models\PrayerRequest;
use App\Notifications\ChurchNotification;
use Illuminate\Support\Str;

/**
 * To pastors / prayer team when a member submits a request: either one to
 * approve for the whole church, or a private one for the pastors only.
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
            ? __('🔒 Private prayer request from :name', ['name' => $this->request->user?->name ?? __('a member')])
            : __('Prayer request to approve from :name', ['name' => $this->request->user?->name ?? __('a member')]);
    }

    public function body(): ?string
    {
        return Str::limit($this->request->body, 160);
    }

    public function url(): string
    {
        return route('church-admin.prayer.index', [$this->request->church, 'status' => $this->request->visibility === 'pastor' ? 'private' : 'pending']);
    }

    public function icon(): string
    {
        return $this->request->visibility === 'pastor' ? 'bi-lock' : 'bi-hourglass-split';
    }

    public function churchName(): ?string
    {
        return $this->request->church?->name;
    }
}
