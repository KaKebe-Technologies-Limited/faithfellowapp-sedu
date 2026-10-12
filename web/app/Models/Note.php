<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A member's private note, tied to what they were reading or watching
 * (a Bible chapter, a reading-plan day, a sermon, a devotional) or free-standing.
 */
class Note extends Model
{
    use SoftDeletes;

    public const COLORS = ['paper' => '#FFFDF7', 'rose' => '#FDF0F3', 'sky' => '#EEF5FF', 'mint' => '#EDF9F2', 'sun' => '#FFF8E6'];

    /** Context prefix => [label, icon] */
    public const KINDS = [
        'bible' => ['Bible', 'bi-book'],
        'plan' => ['Reading plan', 'bi-journal-check'],
        'sermon' => ['Sermon', 'bi-mic'],
        'devotional' => ['Devotional', 'bi-sun'],
        'live' => ['Live service', 'bi-broadcast'],
        'note' => ['Note', 'bi-pencil'],
    ];

    protected $fillable = ['user_id', 'church_id', 'context', 'reference', 'url', 'title', 'body', 'color', 'is_pinned'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function kind(): string
    {
        return array_key_exists($k = Str::before($this->context, ':'), self::KINDS) ? $k : 'note';
    }

    public function displayTitle(): string
    {
        return $this->title ?: ($this->reference ?: Str::limit(strtok((string) $this->body, "\n") ?: __('Untitled note'), 60));
    }

    /**
     * Safe light formatting for display: "# heading", "- bullet", "> quote", **bold**, *italic*.
     */
    public function renderBody(): string
    {
        $html = [];
        $list = false;
        foreach (preg_split('/\R/', (string) $this->body) as $line) {
            $text = e($line);
            $text = preg_replace(['/\*\*(.+?)\*\*/', '/(?<![\*\w])\*(?!\s)(.+?)(?<!\s)\*(?!\*)/'], ['<strong>$1</strong>', '<em>$1</em>'], $text);

            if (preg_match('/^\s*[-•]\s+(.*)$/', $text, $m)) {
                $html[] = ($list ? '' : '<ul>').'<li>'.$m[1].'</li>';
                $list = true;

                continue;
            }
            if ($list) {
                $html[] = '</ul>';
                $list = false;
            }

            $html[] = match (true) {
                (bool) preg_match('/^#\s+(.*)$/', $text, $m) => '<h4>'.$m[1].'</h4>',
                (bool) preg_match('/^&gt;\s?(.*)$/', $text, $m) => '<blockquote>'.$m[1].'</blockquote>',
                trim($text) === '' => '<br>',
                default => '<p>'.$text.'</p>',
            };
        }
        if ($list) {
            $html[] = '</ul>';
        }

        return implode('', $html);
    }
}
