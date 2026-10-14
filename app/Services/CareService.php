<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VolunteerRota;
use App\Notifications\Care\CareDigest;
use App\Notifications\Care\CommunityUpdate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pastoral care: birthdays, anniversaries, follow-ups, weekly digest.
 */
class CareService
{
    /**
     * Members (of this church) with birthdays / anniversaries in the next N days.
     *
     * @return array{birthdays: Collection<int, User>, anniversaries: Collection<int, User>}
     */
    public function upcomingCelebrations(Church $church, int $days = 7): array
    {
        $members = User::with('media')
            ->whereHas('subscriptions', fn ($q) => $q->where('church_id', $church->id)->where('status', 'active'))
            ->where(fn ($q) => $q->whereNotNull('date_of_birth')->orWhereNotNull('wedding_anniversary'))
            ->get();

        $within = function (?Carbon $date) use ($days) {
            if (! $date) {
                return null;
            }
            $next = $date->copy()->year(now()->year);
            if ($next->lt(today())) {
                $next->addYear();
            }

            return $next->lte(today()->addDays($days)) ? $next : null;
        };

        // Clone per list: one person can appear in both, each with its own next_date.
        $upcoming = fn (string $field) => $members
            ->map(fn (User $u) => ($next = $within($u->{$field})) ? tap(clone $u, fn ($c) => $c->next_date = $next) : null)
            ->filter()->sortBy('next_date')->values();

        return [
            'birthdays' => $upcoming('date_of_birth'),
            'anniversaries' => $upcoming('wedding_anniversary'),
        ];
    }

    /**
     * Visitors and new members who haven't been followed up (stage still visitor
     * after joining in the last 30 days).
     *
     * @return Collection<int, Subscription>
     */
    public function followUps(Church $church): Collection
    {
        return $church->subscriptions()->with('user.media')->where('status', 'active')
            ->whereIn('stage', ['visitor', 'new_member'])->where('subscribed_at', '>=', now()->subDays(60))
            ->latest('subscribed_at')->take(30)->get();
    }

    /**
     * Remind volunteers serving tomorrow (once per rota slot).
     */
    public function remindVolunteers(): int
    {
        $sent = 0;
        VolunteerRota::with(['team', 'user', 'church'])
            ->whereDate('service_date', today()->addDay())
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereNull('reminded_at')
            ->each(function (VolunteerRota $rota) use (&$sent) {
                if (! $rota->user || ! $rota->church?->isActive()) {
                    return;
                }
                $rota->user->notify(new CommunityUpdate(
                    $rota->church,
                    __('Reminder: you are serving with :team tomorrow', ['team' => $rota->team->name]),
                    route('volunteering.index'),
                    $rota->status === 'scheduled' ? __('Please confirm you can make it.') : __('Thank you for serving!'),
                    'bi-hand-thumbs-up',
                    'event',
                ));
                $rota->forceFill(['reminded_at' => now()])->saveQuietly();
                $sent++;
            });

        return $sent;
    }

    /**
     * Monday digest to pastoral staff.
     */
    public function sendWeeklyDigests(): int
    {
        $sent = 0;
        Church::active()->each(function (Church $church) use (&$sent) {
            $celebrations = $this->upcomingCelebrations($church);
            $followUps = $this->followUps($church);
            if ($celebrations['birthdays']->isEmpty() && $celebrations['anniversaries']->isEmpty() && $followUps->isEmpty()) {
                return;
            }
            foreach (app(PrayerService::class)->pastoralTeam($church) as $pastor) {
                $pastor->notify(new CareDigest($church, $celebrations['birthdays']->count(), $celebrations['anniversaries']->count(), $followUps->count()));
                $sent++;
            }
        });

        return $sent;
    }
}
