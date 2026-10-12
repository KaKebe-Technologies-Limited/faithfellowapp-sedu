<?php

namespace App\Models;

use App\Support\Bible\Books;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A verse a member coloured in the Bible reader. Highlights belong to the
 * verse, not the translation, so they show in every version. Private to
 * the member.
 */
class BibleHighlight extends Model
{
    /** Colour key => [label, swatch]. */
    public const COLORS = [
        'yellow' => ['Yellow', '#FFE97A'],
        'green' => ['Green', '#A8E6B8'],
        'blue' => ['Blue', '#B5D6FF'],
        'pink' => ['Pink', '#FFC2DA'],
    ];

    protected $fillable = ['user_id', 'book', 'chapter', 'verse', 'color'];

    protected function casts(): array
    {
        return ['chapter' => 'integer', 'verse' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Verse key used by the reader: "JHN.3.16". */
    public function key(): string
    {
        return "{$this->book}.{$this->chapter}.{$this->verse}";
    }

    public function label(): string
    {
        return Books::name($this->book)." {$this->chapter}:{$this->verse}";
    }

    /**
     * A member's highlights for some chapters, keyed "BOOK.chapter.verse" => colour.
     *
     * @param  iterable<int, array{book: string, chapter: int}>  $chapters
     * @return array<string, string>
     */
    public static function mapFor(?User $user, iterable $chapters): array
    {
        $chapters = collect($chapters)->unique(fn ($c) => $c['book'].'.'.$c['chapter']);
        if (! $user || $chapters->isEmpty()) {
            return [];
        }

        return static::where('user_id', $user->id)
            ->where(fn (Builder $q) => $chapters->each(fn ($c) => $q->orWhere(fn ($w) => $w->where('book', $c['book'])->where('chapter', $c['chapter']))))
            ->get()
            ->mapWithKeys(fn (self $h) => [$h->key() => $h->color])
            ->all();
    }
}
