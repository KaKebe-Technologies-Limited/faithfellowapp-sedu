<?php

namespace App\Notifications\Admin;

use App\Models\User;
use App\Notifications\Concerns\BrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the platform owner: a new person has created an account. */
class NewSignup extends Notification implements ShouldQueue
{
    use BrandedMail, Queueable;

    public function __construct(public User $user, public int $total) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $how = $this->user->google_id ? __('Google') : __('Email and password');

        return $this->branded(
            __('New sign-up: :name', ['name' => $this->user->name]),
            __('Someone just joined FaithFellows'),
            [__(':name created an account. You now have :n members.', ['name' => $this->user->name, 'n' => number_format($this->total)])],
            array_filter([
                __('Name') => $this->user->name,
                __('Email') => $this->user->email,
                __('Phone') => $this->user->phone,
                __('Signed up with') => $how,
                __('When') => $this->user->created_at?->timezone(config('app.timezone'))->format('j F Y, H:i'),
            ]),
            ['label' => __('See the members'), 'url' => route('super-admin.users.index')],
            __('You get this email for every new account. Change who receives it in Super admin, Email.'),
        );
    }
}
