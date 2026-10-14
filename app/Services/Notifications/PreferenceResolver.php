<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Works out which channels a user wants for a church + category.
 *
 * Lookup order: church-specific preference → global (church_id NULL)
 * preference → platform defaults. A disabled row means "don't notify".
 */
class PreferenceResolver
{
    /** @var array<int, Collection<int, NotificationPreference>> */
    protected array $cache = [];

    /**
     * @return array<int, string> channel keys, e.g. ['database', 'mail']
     */
    public function channelsFor(User $user, ?int $churchId, string $category): array
    {
        $prefs = $this->cache[$user->id] ??= $user->notificationPreferences()->get();

        $pref = $prefs->first(fn ($p) => $p->church_id === $churchId && $p->category === $category)
            ?? $prefs->first(fn ($p) => $p->church_id === null && $p->category === $category);

        if ($pref) {
            return $pref->enabled ? array_values($pref->channels ?? []) : [];
        }

        return NotificationPreference::DEFAULT_CHANNELS[$category] ?? ['database'];
    }

    /**
     * Save a church × category × channel matrix from the preferences form.
     *
     * @param  array<string, array<string, array<int, string>>>  $matrix  churchKey => category => channels
     */
    public function saveMatrix(User $user, array $matrix): void
    {
        $validChannels = array_keys(config('faithfellows.notification_channels'));
        $validCategories = array_keys(config('faithfellows.notification_categories'));
        $allowedChurches = $user->subscribedChurchIds();

        foreach ($matrix as $churchKey => $categories) {
            $churchId = $churchKey === 'global' ? null : (int) $churchKey;
            if ($churchId !== null && ! in_array($churchId, $allowedChurches, true)) {
                continue;
            }

            foreach ($validCategories as $category) {
                $channels = array_values(array_intersect($categories[$category] ?? [], $validChannels));
                NotificationPreference::updateOrCreate(
                    ['user_id' => $user->id, 'church_id' => $churchId, 'category' => $category],
                    ['channels' => $channels, 'enabled' => $channels !== []],
                );
            }
        }

        unset($this->cache[$user->id]);
    }

    /**
     * Mute or unmute every category for one church.
     */
    public function setChurchMuted(User $user, int $churchId, bool $muted): void
    {
        foreach (array_keys(config('faithfellows.notification_categories')) as $category) {
            $current = $this->channelsFor($user, $churchId, $category);
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'church_id' => $churchId, 'category' => $category],
                [
                    'channels' => $muted ? $current : ($current ?: NotificationPreference::DEFAULT_CHANNELS[$category] ?? ['database']),
                    'enabled' => ! $muted,
                ],
            );
        }
        unset($this->cache[$user->id]);
    }
}
