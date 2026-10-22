<?php

namespace App\Http\Resources;

use App\Models\Connection;
use App\Models\DirectMessage;
use App\Models\User;
use App\Support\AppFeatures;
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
            'nickname' => $this->nickname,
            'game_name' => $this->gameName(),
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'phone' => $this->phone,
            'bio' => $this->bio,
            'city' => $this->city,
            'country' => $this->country,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'interests' => $this->interests ?? [],
            'directory_opt_in' => (bool) $this->directory_opt_in,
            'open_to_connect' => (bool) $this->open_to_connect,
            'has_location' => $this->latitude !== null,
            'avatar_url' => $this->avatar_url,
            'language' => $this->language,
            'timezone' => $this->timezone,
            'preferences' => [
                'digest_mode' => (bool) $this->digest_mode,
                'quiet_hours' => $this->quiet_hours_start ? ['start' => substr($this->quiet_hours_start, 0, 5), 'end' => substr((string) $this->quiet_hours_end, 0, 5)] : null,
                'low_bandwidth' => (bool) $this->low_bandwidth,
            ],
            // Podcasts, hymns and radio: what the super admin switched on for this person's churches (everything for a new sign-up).
            'features' => AppFeatures::for($this->resource),
            'is_super_admin' => $this->isSuperAdmin(),
            'churches' => ChurchSummaryResource::collection($this->whenLoaded('subscribedChurches')),
            'staff_of' => $this->staffMemberships()->where('status', 'active')->with('church')->get()->filter(fn ($s) => $s->church && $s->church->status !== 'archived')->map(fn ($s) => ['id' => $s->church->id, 'slug' => $s->church->slug, 'name' => $s->church->name])->values(),
            'unread_notifications' => $this->unreadNotifications()->count(),
            'unread_messages' => DirectMessage::where('recipient_id', $this->id)->whereNull('read_at')->count(),
            'connection_requests' => Connection::where('addressee_id', $this->id)->where('status', 'pending')->count(),
        ];
    }
}
