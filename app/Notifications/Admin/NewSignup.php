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

    /** "Uganda" for "UG" (the code when this server cannot say the name). */
    protected function country(): ?string
    {
        $code = (string) $this->user->country;

        return $code === '' ? null : ((class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, 'en') : null) ?: $code);
    }

    /** The churches the person already follows, if any (usually none yet: they pick one after signing up). */
    protected function churches(): ?string
    {
        $names = rescue(fn () => $this->user->subscribedChurches()->pluck('churches.name')->take(3)->implode(', '), '', false);

        return $names !== '' ? $names : null;
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
                __('Country') => $this->country(),
                __('Town') => $this->user->city,
                __('Language') => $this->user->language ? (config('faithfellows.languages.'.$this->user->language) ?: $this->user->language) : null,
                __('Follows') => $this->churches(),
                __('Member number') => '#'.number_format($this->total),
                __('When') => $this->user->created_at?->timezone(config('app.timezone'))->format('j F Y, H:i'),
            ]),
            ['label' => __('Open this member'), 'url' => route('super-admin.users.show', $this->user)],
            __('You get this email for every new account. Change who receives it in Super admin, Email.'),
        );
    }
}
