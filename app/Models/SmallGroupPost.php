<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A message in a group's conversation (study group, cell, fellowship). */
class SmallGroupPost extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = ['church_id', 'small_group_id', 'user_id', 'body'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SmallGroup::class, 'small_group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
