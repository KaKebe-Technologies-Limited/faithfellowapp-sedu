<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** One hymn with its verses, an optional refrain and the credits of its words and music. */
class Hymn extends Model
{
    use SoftDeletes;

    public const COPYRIGHT = ['public_domain' => 'Public domain', 'licensed' => 'Used with permission', 'original' => 'Original, shared by its author'];

    protected $fillable = [
        'hymnal_id', 'number', 'title', 'first_line', 'verses', 'refrain', 'search_text', 'author', 'translator', 'composer', 'arranger', 'tune', 'meter', 'key_signature',
        'year_written', 'themes', 'scripture', 'copyright_status', 'credit_note', 'source_url', 'audio_url', 'sheet_music_url', 'status', 'favorites_count',
    ];

    protected function casts(): array
    {
        return ['verses' => 'array', 'refrain' => 'array', 'themes' => 'array', 'year_written' => 'integer', 'number' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (Hymn $hymn) {
            $hymn->first_line = $hymn->first_line ?: $hymn->deriveFirstLine();
            $hymn->search_text = $hymn->buildSearchText();
        });
        static::saved(fn (Hymn $hymn) => $hymn->hymnal?->refreshCount());
        static::deleted(fn (Hymn $hymn) => $hymn->hymnal?->refreshCount());
    }

    public function hymnal(): BelongsTo
    {
        return $this->belongsTo(Hymnal::class)->withTrashed();
    }

    public function favoredBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hymn_favorites')->withTimestamps();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereHas('hymnal', fn ($h) => $h->where('is_published', true));
    }

    /**
     * The text typed by the super admin → verses and refrain. Paragraphs (blank-line separated) are verses; a paragraph
     * that starts with "Refrain" or "Chorus" (with or without a colon) is the refrain. A leading "1." is allowed and ignored.
     *
     * @return array{verses: list<array{number: int, lines: list<string>}>, refrain: array{lines: list<string>}|null}
     */
    public static function parseLyrics(string $text): array
    {
        $verses = [];
        $refrain = null;
        foreach (preg_split('/\R\s*\R/', trim(str_replace("\r", '', $text))) ?: [] as $block) {
            $lines = array_values(array_filter(array_map(fn ($l) => trim(preg_replace('/\s+/', ' ', $l)), explode("\n", $block)), fn ($l) => $l !== ''));
            if ($lines === []) {
                continue;
            }
            if (preg_match('/^(refrain|chorus)\s*:?\s*(.*)$/i', $lines[0], $m)) {
                $lines[0] = trim($m[2]);
                $refrain = ['lines' => array_values(array_filter($lines, fn ($l) => $l !== ''))];

                continue;
            }
            $lines[0] = preg_replace('/^(verse\s*)?\d+[.):]\s*/i', '', $lines[0]);
            $verses[] = ['number' => count($verses) + 1, 'lines' => array_values(array_filter($lines, fn ($l) => $l !== ''))];
        }

        return ['verses' => $verses, 'refrain' => $refrain];
    }

    /** The lyrics as the super admin edits them (the reverse of parseLyrics). */
    public function lyricsForEditing(): string
    {
        $blocks = [];
        foreach ($this->verses ?? [] as $verse) {
            $blocks[] = ($verse['number'] ?? count($blocks) + 1).'. '.implode("\n", $verse['lines']);
        }
        if ($this->refrain) {
            $blocks[] = "Refrain:\n".implode("\n", $this->refrain['lines']);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * What the app shows, in singing order: verse 1, the refrain, verse 2, the refrain…
     *
     * @return list<array{type: string, label: string, lines: list<string>}>
     */
    public function sections(): array
    {
        $sections = [];
        foreach ($this->verses ?? [] as $verse) {
            $sections[] = ['type' => 'verse', 'label' => (string) ($verse['number'] ?? count($sections) + 1), 'lines' => $verse['lines']];
            if ($this->refrain && ($this->refrain['lines'] ?? [])) {
                $sections[] = ['type' => 'refrain', 'label' => __('Refrain'), 'lines' => $this->refrain['lines']];
            }
        }

        return $sections;
    }

    /**
     * The credits in lines for the hymn page: who wrote the words, who wrote the music, the licence and where the text was
     * collected.
     *
     * @return list<array{label: string, value: string}>
     */
    public function creditLines(): array
    {
        $lines = [];
        $words = trim($this->author.($this->year_written ? ', '.$this->year_written : ''), ', ');
        if ($words !== '') {
            $lines[] = ['label' => __('Words'), 'value' => $words];
        }
        if ($this->translator) {
            $lines[] = ['label' => __('Translated by'), 'value' => $this->translator];
        }
        $music = trim(($this->tune ? '“'.$this->tune.'”' : '').($this->composer ? ($this->tune ? ', ' : '').$this->composer : ''));
        if ($music !== '') {
            $lines[] = ['label' => __('Music'), 'value' => $music];
        }
        if ($this->arranger) {
            $lines[] = ['label' => __('Setting'), 'value' => $this->arranger];
        }
        $lines[] = ['label' => __('Copyright'), 'value' => __(self::COPYRIGHT[$this->copyright_status] ?? 'Public domain')];
        $source = $this->hymnal?->source_name;
        if ($source) {
            $lines[] = ['label' => __('Text collected from'), 'value' => $source.($this->hymnal->source_url ? ' ('.preg_replace('#^https?://(www\.)?#', '', rtrim($this->hymnal->source_url, '/')).')' : '')];
        }

        return $lines;
    }

    /** The whole hymn as plain text for sharing (WhatsApp and the like), credits included. */
    public function shareText(): string
    {
        $out = [];
        $out[] = ($this->number ? $this->number.'. ' : '').$this->title;
        foreach ($this->sections() as $s) {
            $out[] = '';
            $out[] = $s['type'] === 'refrain' ? $s['label'].':' : $s['label'].'.';
            array_push($out, ...$s['lines']);
        }
        $out[] = '';
        $out[] = collect($this->creditLines())->take(3)->map(fn ($c) => $c['label'].': '.$c['value'])->implode("\n");
        $out[] = __('From the FaithFellows hymn book');

        return trim(implode("\n", $out));
    }

    protected function deriveFirstLine(): ?string
    {
        $first = $this->verses[0]['lines'][0] ?? null;

        return $first ? Str::limit(rtrim($first, ',;:.!? '), 250, '') : null;
    }

    protected function buildSearchText(): string
    {
        $lines = collect($this->verses ?? [])->pluck('lines')->flatten()->merge($this->refrain['lines'] ?? [])->push($this->title)->push((string) $this->author)->push((string) $this->composer);

        return Str::lower(preg_replace('/[^\p{L}\p{N}\s\']+/u', ' ', $lines->implode(' ')) ?? '');
    }
}
