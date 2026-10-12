<?php

namespace App\Notifications\Content;

use App\Models\Sermon;
use App\Notifications\ChurchNotification;

class NewSermonPublished extends ChurchNotification
{
    public string $category = 'sermon';

    public function __construct(public Sermon $sermon)
    {
        $this->churchId = $sermon->church_id;
    }

    public function title(): string
    {
        return __('New sermon: :title', ['title' => $this->sermon->title]);
    }

    public function body(): ?string
    {
        return collect([$this->sermon->preacher, $this->sermon->scripture])->filter()->implode(' · ') ?: null;
    }

    public function url(): string
    {
        return route('sermons.show', $this->sermon);
    }

    public function icon(): string
    {
        return 'bi-mic';
    }

    public function churchName(): ?string
    {
        return $this->sermon->church?->name;
    }
}
