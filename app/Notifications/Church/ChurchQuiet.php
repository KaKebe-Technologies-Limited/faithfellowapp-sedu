<?php

namespace App\Notifications\Church;

use App\Models\Church;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A warning to a church that has been dormant: nothing posted, nobody from the staff signed in, and no new followers for a while. It says when the
 * church will be blocked if things stay quiet, and that signing in and posting something is enough to keep it open.
 */
class ChurchQuiet extends Notification implements ShouldQueue
{
    use BrandedMail, Queueable;

    public function __construct(public Church $church, public int $quietDays, public int $blockInDays) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $blockOn = now()->addDays($this->blockInDays)->format('j F Y');

        return $this->branded(
            __(':church has been quiet on FaithFellows', ['church' => $this->church->name]),
            __('Keep your church active'),
            [
                __('Hello :name,', ['name' => $notifiable->first_name ?: $notifiable->name]),
                __('We have not seen any activity at :church for :days days: no sign-in by the staff, nothing new posted and no new followers.', ['church' => $this->church->name, 'days' => $this->quietDays]),
                __('To keep the church page and its place in the app, please sign in and share something with your members, for example an announcement, a sermon, an event or a devotional. If it stays quiet, the church will be blocked on :date.', ['date' => $blockOn]),
                __('Need help getting started? Reply to this email and our team will walk you through it.'),
            ],
            [__('Quiet for') => trans_choice(':n day|:n days', $this->quietDays, ['n' => $this->quietDays]), __('Blocked on') => $blockOn],
            ['label' => __('Open my church portal'), 'url' => route('church-admin.dashboard', $this->church)],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your church has been quiet'),
            'body' => __('Share something with your members within :days days to keep it active.', ['days' => $this->blockInDays]),
            'icon' => 'bi-moon',
            'url' => route('church-admin.dashboard', $this->church),
            'church' => $this->church->name,
        ];
    }
}
