<?php

namespace App\Notifications\Member;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * One email a day for members in digest mode, summarising what their
 * churches sent since the last digest.
 */
class DailyDigest extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, array{title: string, church: ?string, url: ?string}>  $items
     */
    public function __construct(public Collection $items, public int $total) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(trans_choice('Your FaithFellows digest: :count update|Your FaithFellows digest: :count updates', $this->total))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? '']))
            ->line(__('Here is what your churches shared today:'));

        foreach ($this->items->groupBy(fn ($i) => $i['church'] ?: __('FaithFellows')) as $church => $items) {
            $mail->line('**'.$church.'**');
            foreach ($items as $item) {
                $mail->line('• '.$item['title']);
            }
        }

        if ($this->total > $this->items->count()) {
            $mail->line(__('…and :n more.', ['n' => $this->total - $this->items->count()]));
        }

        return $mail->action(__('Open my notifications'), route('notifications.index'))
            ->line(__('You get one email a day because digest mode is on. Change it in Profile → Preferences.'));
    }
}
