<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per notification × recipient × channel. Feeds delivery analytics
 * and SMS/WhatsApp cost reporting. Pruned after 180 days.
 */
class NotificationDelivery extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = ['church_id', 'user_id', 'church_broadcast_id', 'notification', 'category', 'channel', 'status', 'cost', 'provider_ref', 'error'];

    protected function casts(): array
    {
        return ['cost' => 'decimal:2'];
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(180));
    }
}
