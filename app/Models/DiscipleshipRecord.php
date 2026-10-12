<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * New believers' growth journey milestones.
 */
class DiscipleshipRecord extends Model
{
    use BelongsToChurch;

    public const MILESTONES = [
        'salvation' => 'Accepted Christ',
        'foundations' => 'Foundations class',
        'baptism' => 'Water baptism',
        'membership' => 'Membership class',
        'small_group' => 'Joined a small group',
        'serving' => 'Serving on a team',
        'leadership' => 'Leadership training',
    ];

    protected $fillable = ['church_id', 'user_id', 'milestone', 'completed_on', 'mentor_id', 'notes'];

    protected function casts(): array
    {
        return ['completed_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
