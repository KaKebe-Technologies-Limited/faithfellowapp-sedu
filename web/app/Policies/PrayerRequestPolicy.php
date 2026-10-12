<?php

namespace App\Policies;

use App\Models\PrayerRequest;
use App\Models\User;

class PrayerRequestPolicy
{
    /**
     * The author and the church's pastoral team always see a request.
     * Everyone else only sees approved (open/answered) requests their
     * visibility allows; pastor-only requests are never shown to members.
     */
    public function view(?User $user, PrayerRequest $request): bool
    {
        if ($user && ($request->user_id === $user->id || $this->pastoral($user, $request))) {
            return true;
        }

        if (! $request->isShared() || ! $request->church?->isActive()) {
            return false;
        }

        return match ($request->visibility) {
            'public' => true,
            'church' => $user?->isMemberOf($request->church_id) ?? false,
            default => false, // pastor-only
        };
    }

    public function pray(User $user, PrayerRequest $request): bool
    {
        return $request->isShared() && $this->view($user, $request);
    }

    public function update(User $user, PrayerRequest $request): bool
    {
        return $request->user_id === $user->id;
    }

    public function moderate(User $user, PrayerRequest $request): bool
    {
        return $user->canInChurch($request->church_id, 'prayer.moderate');
    }

    protected function pastoral(User $user, PrayerRequest $request): bool
    {
        return $user->canInChurch($request->church_id, 'prayer.moderate') || $user->canInChurch($request->church_id, 'care.manage');
    }
}
