<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One kind of activity of one member on one day: seconds spent and/or how many times. */
class MemberActivity extends Model
{
    protected $table = 'member_activity';

    /** kind => what is added up. The app may only send these. */
    public const KINDS = [
        'read' => 'seconds',            // time with the Bible open
        'chapters' => 'count',          // Bible chapters opened
        'radio' => 'seconds',           // time listening to the gospel radio
        'podcast' => 'seconds',         // time listening to podcast episodes
        'share_scripture' => 'count',   // verses and passages shared
        'share_inspiration' => 'count', // inspiring messages, devotionals and verses of the day shared
        'open' => 'count',              // times the app was opened that day
    ];

    protected $fillable = ['user_id', 'day', 'kind', 'seconds', 'count'];

    protected function casts(): array
    {
        return ['day' => 'date', 'seconds' => 'integer', 'count' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
