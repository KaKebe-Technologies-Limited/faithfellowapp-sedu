<?php

namespace App\Support;

use App\Models\Church;
use App\Models\User;

/**
 * Which platform features a person sees in the app: podcasts, the hymn book and the gospel radio.
 *
 * They belong to the whole platform. The super admin decides, church by church (Super admin, App features), whether a church's
 * members see them. The rule for a person:
 *  - someone who has not joined a church yet (a new sign-up) sees all of them;
 *  - a super admin sees all of them;
 *  - anyone else sees a feature when at least one of their churches (the ones they follow, or work for) has it switched on.
 */
class AppFeatures
{
    /** Feature key => the church column that switches it, and its name for people. */
    public const FEATURES = [
        'podcasts' => ['column' => 'show_podcasts', 'label' => 'Podcasts'],
        'hymns' => ['column' => 'show_hymns', 'label' => 'Hymn book'],
        'radio' => ['column' => 'show_radio', 'label' => 'Gospel radio'],
    ];

    /** @return array<string, bool> */
    public static function for(?User $user): array
    {
        $all = array_fill_keys(array_keys(self::FEATURES), true);
        if (! $user || $user->hasRole('super_admin')) {
            return $all;
        }

        $ids = $user->subscribedChurches()->pluck('churches.id')
            ->merge($user->staffMemberships()->where('status', 'active')->pluck('church_id'))
            ->unique()->values();
        if ($ids->isEmpty()) {
            return $all;
        }

        $churches = Church::whereIn('id', $ids)->where('status', 'active')->get(array_column(self::FEATURES, 'column'));
        if ($churches->isEmpty()) {
            return $all;
        }

        return collect(self::FEATURES)->map(fn (array $feature) => $churches->contains(fn (Church $church) => (bool) $church->getAttribute($feature['column'])))->all();
    }

    public static function allows(?User $user, string $feature): bool
    {
        return self::for($user)[$feature] ?? true;
    }
}
