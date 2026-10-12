<?php

namespace App\Notifications\Care;

use App\Models\Church;
use App\Notifications\ChurchNotification;

/**
 * Weekly pastoral digest: birthdays, anniversaries, people to follow up.
 */
class CareDigest extends ChurchNotification
{
    public string $category = 'announcement';

    public function __construct(public Church $church, public int $birthdays, public int $anniversaries, public int $followUps)
    {
        $this->churchId = $church->id;
        $this->forceChannels = ['database', 'mail'];
    }

    public function title(): string
    {
        return __('This week at :church: :b birthdays, :a anniversaries, :f to follow up', [
            'church' => $this->church->name, 'b' => $this->birthdays, 'a' => $this->anniversaries, 'f' => $this->followUps,
        ]);
    }

    public function body(): ?string
    {
        return __('A short call or message goes a long way. Open the care dashboard for names and numbers.');
    }

    public function url(): string
    {
        return route('church-admin.care.index', $this->church);
    }

    public function icon(): string
    {
        return 'bi-heart-pulse';
    }

    public function churchName(): ?string
    {
        return $this->church->name;
    }
}
