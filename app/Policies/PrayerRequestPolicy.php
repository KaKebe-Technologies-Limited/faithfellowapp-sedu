<?php

namespace App\Policies;

use App\Models\PrayerRequest;
use App\Models\User;

class PrayerRequestPolicy
{
    public function view(?User $user, PrayerRequest $request): bool
    {
        if ($user && ($request->user_id === $user->id || $user->canInChurch($request->church_id, 'prayer.moderate') || $user->canInChurch($request->church_id, 'care.manage'))) {
            return true;
        }

        if ($request->status === 'hidden' || ! $request->church?->isActive()) {
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
        return $this->view($user, $request) && $request->status !== 'hidden';
    }

    public function update(User $user, PrayerRequest $request): bool
    {
        return $request->user_id === $user->id;
    }

    public function moderate(User $user, PrayerRequest $request): bool
    {
        return $user->canInChurch($request->church_id, 'prayer.moderate');
    }
}
