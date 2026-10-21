<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\Admin\NewSignup;
use Illuminate\Support\Facades\Notification;

/**
 * "Someone just joined": an email to the platform owner for every new account, so growth never goes unnoticed. It is on by default and goes to the
 * addresses the super admin lists (Super admin, Email), or to every super admin's own email when none is listed.
 */
class SignupAlerts
{
    public function enabled(): bool
    {
        return (string) PlatformSetting::get('signup_alerts', '1') !== '0';
    }

    /** The addresses typed by the super admin (separated by commas, spaces or new lines). */
    public function listed(): string
    {
        return (string) PlatformSetting::get('signup_alert_emails', '');
    }

    /** @return array<int, string> */
    public function recipients(): array
    {
        $listed = collect(preg_split('/[\s,;]+/', $this->listed(), -1, PREG_SPLIT_NO_EMPTY))->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->map(fn ($e) => mb_strtolower($e));

        if ($listed->isEmpty()) {
            $listed = User::role('super_admin')->whereNotNull('email')->where('status', 'active')->pluck('email')->map(fn ($e) => mb_strtolower($e));
        }

        return $listed->unique()->values()->all();
    }

    /** @param  array<int, string>|string  $emails  at most a few addresses; blank clears the list */
    public function save(bool $enabled, string $emails): void
    {
        PlatformSetting::put('signup_alerts', $enabled ? '1' : '0');
        PlatformSetting::put('signup_alert_emails', trim($emails));
    }

    /** Tells the owner about one new account. */
    public function notify(User $user): int
    {
        if (! $this->enabled() || $user->hasRole('super_admin')) {
            return 0;
        }

        // Sent at once, not put on the queue: this already runs after the sign-up has been answered, and a shared server may have no queue worker.
        $sent = 0;
        foreach (array_values(array_diff($this->recipients(), [mb_strtolower((string) $user->email)])) as $address) {
            $sent += rescue(fn () => Notification::route('mail', $address)->notifyNow(new NewSignup($user, User::count())) ?? 1, 0) ?: 0;
        }

        return $sent;
    }
}
