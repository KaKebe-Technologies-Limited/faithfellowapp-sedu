<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone running the app that can receive push notifications (an Expo push token).
 * Tokens the push service reports as unregistered are deleted.
 */
class DeviceToken extends Model
{
    /** Per member; the oldest are dropped beyond this. */
    public const MAX_PER_USER = 8;

    protected $fillable = ['user_id', 'token', 'platform', 'device_name', 'app_version', 'last_used_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function looksValid(string $token): bool
    {
        return (bool) preg_match('/^(Expo|Exponent)PushToken\[[A-Za-z0-9_\-]{8,}\]$/', $token);
    }
}
