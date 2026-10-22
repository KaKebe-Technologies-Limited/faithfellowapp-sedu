<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class Reaction extends Model
{
    use BelongsToChurch;

    /** Every reaction there is. like and smile need the database update that turned the column into text (see `available()`). */
    public const TYPES = ['like' => '👍', 'love' => '❤️', 'smile' => '😊', 'amen' => '🙏', 'pray' => '🕊️', 'celebrate' => '🎉'];

    /** The ones the very first version of the database could hold. */
    public const ORIGINAL = ['amen', 'love', 'pray', 'celebrate'];

    /** True once the database update that lets the table hold like and smile has been applied. */
    public static function extended(): bool
    {
        static $extended = null;

        return $extended ??= (bool) rescue(fn () => Schema::getColumnType('reactions', 'type') !== 'enum', false, false);
    }

    /** @return array<string, string> the reactions people can use right now: key => emoji */
    public static function available(): array
    {
        return self::extended() ? self::TYPES : Arr::only(self::TYPES, self::ORIGINAL);
    }

    protected $fillable = ['church_id', 'reactable_type', 'reactable_id', 'user_id', 'type'];

    public function reactable(): MorphTo
    {
        return $this->morphTo();
    }
}
