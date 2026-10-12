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
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'denomination' => $this->denomination,
            'description' => $this->description,
            'pastor' => $this->pastor_name,
            'languages' => $this->languages ?? [],
            'location' => ['city' => $this->city, 'district' => $this->district, 'address' => $this->address, 'lat' => $this->lat ? (float) $this->lat : null, 'lng' => $this->lng ? (float) $this->lng : null],
            'contact' => ['phone' => $this->phone, 'email' => $this->email, 'website' => $this->website],
            'brand_color' => $this->brand_color,
            'logo_url' => $this->logo_url,
            'cover_url' => $this->cover_url,
            'web_url' => route('churches.show', $this->resource),
            'subscribers' => $this->whenCounted('subscriptions'),
            'is_subscribed' => $this->when($request->user() !== null, fn () => $request->user()->isSubscribedTo($this->id)),
            'service_times' => $this->whenLoaded('serviceTimes', fn () => $this->serviceTimes->map(fn ($s) => [
                'name' => $s->name, 'day' => $s->day_name, 'time' => $s->time_range, 'language' => $s->language, 'location' => $s->location,
            ])),
            'giving_methods' => $this->whenLoaded('givingChannels', fn () => $this->givingChannels->where('is_active', true)->pluck('type')->values()),
        ];
    }
}
