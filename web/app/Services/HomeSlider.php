<?php

namespace App\Services;

use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The member home-screen slider: photos from every followed church that has
 * at least four, taking turns (A1, B1, C1, A2, B2, C2…) so no church dominates.
 */
class HomeSlider
{
    /**
     * @return Collection<int, array{image: string, image_sm: string, title: string, subtitle: ?string, church: string, church_id: int, church_slug: string, logo: ?string, url: string}>
     */
    public function forUser(User $user, ?Church $only = null): Collection
    {
        $churches = $only
            ? collect([$only->loadMissing('media')])
            : Church::active()->with('media')->whereIn('id', $user->subscribedChurchIds() ?: [0])->orderBy('name')->get();

        $decks = $churches->filter(fn (Church $c) => $c->hasSlider())
            ->map(fn (Church $c) => $c->slides()->map(fn ($m) => [
                'image' => $m->getUrl('slide'),
                'image_sm' => $m->getUrl('slide-sm'),
                'title' => $m->getCustomProperty('title') ?: $c->name,
                'subtitle' => $m->getCustomProperty('subtitle') ?: $c->tagline,
                'church' => $c->name,
                'church_id' => $c->id,
                'church_slug' => $c->slug,
                'logo' => $c->logo_url,
                'url' => route('churches.show', $c),
            ])->values())
            ->values();

        $slides = collect();
        $rounds = $decks->max(fn ($d) => $d->count()) ?? 0;
        for ($r = 0; $r < $rounds; $r++) {
            foreach ($decks as $deck) {
                if (isset($deck[$r])) {
                    $slides->push($deck[$r]);
                }
            }
        }

        return $slides->take(24);
    }
}
