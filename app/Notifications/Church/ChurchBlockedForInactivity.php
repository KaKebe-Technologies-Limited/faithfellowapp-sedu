<?php

namespace App\Notifications\Church;

use App\Models\Church;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The church stayed dormant after the warning, so it was blocked. Tells how to get it open again. */
class ChurchBlockedForInactivity extends Notification implements ShouldQueue
{
    use BrandedMail, Queueable;

    public function __construct(public Church $church) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->branded(
            __(':church has been blocked', ['church' => $this->church->name]),
            __('Your church has been blocked'),
            [
                __('Hello :name,', ['name' => $notifiable->first_name ?: $notifiable->name]),
                __(':church stayed inactive after our warning, so its page and its access in the app have been paused.', ['church' => $this->church->name]),
                __('Nothing was deleted. To open it again, reply to this email or call us at :phone and tell us you are ready to start. We will switch it back on and help you get going.', ['phone' => config('faithfellows.company.phone')]),
            ],
            [],
            null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your church has been blocked'),
            'body' => __('It stayed inactive. Contact us to open it again.'),
            'icon' => 'bi-pause-circle',
            'url' => url('/'),
            'church' => $this->church->name,
        ];
    }
}
