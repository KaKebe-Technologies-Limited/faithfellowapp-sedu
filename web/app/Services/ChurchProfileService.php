<?php

namespace App\Services;

use App\Models\Church;
use App\Models\ChurchSocialLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Church profile writes shared by the setup wizard and settings pages.
 */
class ChurchProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(Church $church, array $data): Church
    {
        $church->update($data);

        return $church;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateContact(Church $church, array $data): Church
    {
        DB::transaction(function () use ($church, $data) {
            $church->update(collect($data)->except('social')->all());
            $this->syncSocialLinks($church, $data['social'] ?? []);
        });

        return $church;
    }

    /**
     * @param  array<string, string|null>  $links  platform => url
     */
    public function syncSocialLinks(Church $church, array $links): void
    {
        foreach ($links as $platform => $url) {
            if (! array_key_exists($platform, ChurchSocialLink::PLATFORMS)) {
                continue;
            }
            if ($url) {
                $church->socialLinks()->updateOrCreate(['platform' => $platform], ['url' => $url]);
            } else {
                $church->socialLinks()->where('platform', $platform)->delete();
            }
        }
    }

    public function updateBranding(Church $church, Request $request): Church
    {
        $data = $request->validate([
            'brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_logo' => ['boolean'],
            'remove_cover' => ['boolean'],
        ]);

        $church->update(['brand_color' => $data['brand_color']]);

        if ($request->boolean('remove_logo')) {
            $church->clearMediaCollection('logo');
        }
        if ($request->boolean('remove_cover')) {
            $church->clearMediaCollection('cover');
        }
        if ($request->hasFile('logo')) {
            $church->addMediaFromRequest('logo')->toMediaCollection('logo');
        }
        if ($request->hasFile('cover')) {
            $church->addMediaFromRequest('cover')->toMediaCollection('cover');
        }

        return $church;
    }

    /**
     * Replace the church's service times with the submitted list.
     *
     * @param  array<int, array<string, mixed>>  $services
     */
    public function syncServiceTimes(Church $church, array $services): void
    {
        DB::transaction(function () use ($church, $services) {
            $church->serviceTimes()->delete();
            foreach ($services as $service) {
                $church->serviceTimes()->create([
                    'name' => $service['name'],
                    'day_of_week' => $service['day_of_week'],
                    'start_time' => $service['start_time'],
                    'end_time' => $service['end_time'] ?? null,
                    'language' => $service['language'] ?? 'en',
                    'branch_id' => $service['branch_id'] ?? null,
                    'location' => $service['location'] ?? null,
                ]);
            }
        });
    }
}
