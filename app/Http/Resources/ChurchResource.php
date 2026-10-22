<?php

namespace App\Http\Resources;

use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Church */
class ChurchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // The church directory routes are public, so the app's token user is only found through the sanctum guard.
        $viewer = $request->user() ?? $request->user('sanctum');
        $contacts = $this->resource->contactsVisibleTo($viewer);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'denomination' => $this->denomination,
            'description' => $this->description,
            'pastor' => $this->pastor_name,
            'languages' => $this->languages ?? [],
            'location' => ['city' => $this->city, 'district' => $this->district, 'address' => $contacts ? $this->address : null, 'lat' => $this->lat ? (float) $this->lat : null, 'lng' => $this->lng ? (float) $this->lng : null],
            // Contact details are for subscribers only (null for everyone else).
            'contact' => $contacts ? ['phone' => $this->phone, 'email' => $this->email, 'website' => $this->website] : null,
            'brand_color' => $this->brand_color,
            'logo_url' => $this->logo_url,
            'cover_url' => $this->cover_url,
            'web_url' => route('churches.show', $this->resource),
            // The church's invite link (null until the database update that adds join codes has been applied).
            'join_url' => $this->join_code ? route('join.show', $this->join_code) : null,
            'subscribers' => $this->whenCounted('subscriptions'),
            'is_subscribed' => $this->when($viewer !== null, fn () => $viewer->isSubscribedTo($this->id)),
            'service_times' => $this->whenLoaded('serviceTimes', fn () => $this->serviceTimes->map(fn ($s) => [
                'name' => $s->name, 'day' => $s->day_name, 'time' => $s->time_range, 'language' => $s->language, 'location' => $s->location,
            ])),
            'giving_methods' => $this->whenLoaded('givingChannels', fn () => $this->givingChannels->where('is_active', true)->pluck('type')->values()),
        ];
    }
}
