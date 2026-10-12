<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user (only ever returned to themselves).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'language' => $this->language,
            'timezone' => $this->timezone,
            'preferences' => [
                'digest_mode' => (bool) $this->digest_mode,
                'quiet_hours' => $this->quiet_hours_start ? ['start' => substr($this->quiet_hours_start, 0, 5), 'end' => substr((string) $this->quiet_hours_end, 0, 5)] : null,
                'low_bandwidth' => (bool) $this->low_bandwidth,
            ],
            'churches' => ChurchSummaryResource::collection($this->whenLoaded('subscribedChurches')),
            'unread_notifications' => $this->unreadNotifications()->count(),
        ];
    }
}
