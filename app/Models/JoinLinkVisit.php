<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One opening of a church's join link (https://faithfellows.com/join/CODE). It feeds the church's figures, and for a few days it carries a hash of
 * the visitor's address and phone kind so the app, opened for the first time right after, can offer to join that church. See JoinLinks.
 */
class JoinLinkVisit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['church_id', 'platform', 'fingerprint', 'claimed_at'];

    protected function casts(): array
    {
        return ['claimed_at' => 'datetime'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
