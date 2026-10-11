<?php

namespace App\Notifications\Content;

use App\Models\Devotional;
use App\Notifications\ChurchNotification;

class NewDevotional extends ChurchNotification
{
    public string $category = 'devotional';

    public function __construct(public Devotional $devotional)
    {
        $this->churchId = $devotional->church_id;
    }

    public function title(): string
    {
        return __('Today’s devotional: :title', ['title' => $this->devotional->title]);
    }

    public function body(): ?string
    {
        return $this->devotional->scripture_text
            ? '“'.$this->devotional->scripture_text.'” — '.$this->devotional->scripture
            : $this->devotional->scripture;
    }

    public function url(): string
    {
        return route('devotionals.show', $this->devotional);
    }

    public function icon(): string
    {
        return 'bi-book';
    }

    public function churchName(): ?string
    {
        return $this->devotional->church?->name;
    }
}
