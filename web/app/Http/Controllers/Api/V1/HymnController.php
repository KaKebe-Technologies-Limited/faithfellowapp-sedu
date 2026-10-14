<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Hymn;
use App\Models\Hymnal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The hymn book in the app: hymnals to choose from, the hymns of a hymnal, search by number, title, first line or any
 * words, a hymn with its verses, refrain and credits, favourites, and a whole hymnal in one download so it can be used
 * with no internet. Only published hymnals and hymns are shown; they are added by the super admin on the website.
 */
class HymnController extends Controller
{
    public function hymnals(Request $request): JsonResponse
    {
        $favorites = $request->user()->favoriteHymns()->published()->count();

        return response()->json(['data' => [
            'hymnals' => Hymnal::published()->orderBy('sort')->orderBy('name')->get()->map(fn (Hymnal $h) => $this->hymnalCard($h))->values(),
            'favorites' => $favorites,
            'languages' => Hymnal::published()->pluck('language')->unique()->values()->map(fn ($l) => ['key' => $l, 'label' => Hymnal::LANGUAGES[$l] ?? strtoupper($l)])->values(),
        ]]);
    }

    /** A hymnal and its hymns (numbers, titles and first lines; no lyrics), optionally filtered by words or a theme. */
    public function hymnal(Request $request, Hymnal $hymnal): JsonResponse
    {
        abort_unless($hymnal->is_published, 404);
        $request->validate(['q' => ['nullable', 'string', 'max:80'], 'theme' => ['nullable', 'string', 'max:60']]);

        $all = $hymnal->hymns()->where('status', 'published')->orderBy('section_order')->orderByRaw('number is null')->orderBy('number')->orderBy('title')->get();
        $favorites = $request->user()->favoriteHymns()->pluck('hymns.id')->all();
        $themes = $all->pluck('themes')->flatten()->filter()->countBy()->sortDesc()->take(24)->map(fn ($n, $t) => ['name' => $t, 'count' => $n])->values();

        $hymns = $all
            ->when($request->filled('theme'), fn ($c) => $c->filter(fn (Hymn $h) => in_array($request->query('theme'), $h->themes ?? [], true)))
            ->when($request->filled('q'), fn ($c) => $c->filter(fn (Hymn $h) => $this->matches($h, (string) $request->query('q'))));

        return response()->json(['data' => $this->hymnalCard($hymnal) + [
            'sections' => $hymnal->sectionList(),
            'themes' => $themes,
            'hymns' => $hymns->map(fn (Hymn $h) => $this->card($h, in_array($h->id, $favorites, true), $hymnal) + ['search' => $h->search_text])->values(),
        ]]);
    }

    /** The whole hymnal with every lyric, to keep on the phone. */
    public function download(Request $request, Hymnal $hymnal): JsonResponse
    {
        abort_unless($hymnal->is_published, 404);
        $favorites = $request->user()->favoriteHymns()->pluck('hymns.id')->all();

        return response()->json(['data' => $this->hymnalCard($hymnal) + [
            'downloaded_at' => now()->toIso8601String(),
            'sections' => $hymnal->sectionList(),
            'hymns' => $hymnal->hymns()->where('status', 'published')->orderBy('section_order')->orderByRaw('number is null')->orderBy('number')->orderBy('title')->get()
                ->map(fn (Hymn $h) => $this->detail($h->setRelation('hymnal', $hymnal), in_array($h->id, $favorites, true)))->values(),
        ]]);
    }

    public function show(Request $request, Hymn $hymn): JsonResponse
    {
        $hymn->load('hymnal');
        abort_unless($hymn->status === 'published' && $hymn->hymnal->is_published, 404);

        return response()->json(['data' => $this->detail($hymn, $request->user()->favoriteHymns()->whereKey($hymn->id)->exists()) + $this->neighbours($hymn)]);
    }

    /** Search every hymnal: a number ("23"), a title, a first line, an author or any words of the hymn. */
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:1', 'max:80'], 'hymnal' => ['nullable', 'integer'], 'language' => ['nullable', 'string', 'max:8']]);
        $q = trim($data['q']);
        $words = collect(preg_split('/\s+/', Str::lower(preg_replace('/[^\p{L}\p{N}\s\']+/u', ' ', $q)), -1, PREG_SPLIT_NO_EMPTY))->take(6);

        $query = Hymn::published()->with('hymnal')
            ->when($request->filled('hymnal'), fn ($h) => $h->where('hymnal_id', (int) $data['hymnal']))
            ->when($request->filled('language'), fn ($h) => $h->whereHas('hymnal', fn ($hy) => $hy->where('language', $data['language'])));

        if (ctype_digit($q)) {
            $query->where(fn ($w) => $w->where('number', (int) $q)->orWhere('title', 'like', '%'.$q.'%'));
        } else {
            $words->each(fn ($word) => $query->where('search_text', 'like', '%'.addcslashes($word, '%_').'%'));
        }
        $found = $query->orderByRaw('title like ? desc', [Str::lower($q).'%'])->orderBy('section_order')->orderBy('number')->limit(40)->get();
        $favorites = $request->user()->favoriteHymns()->pluck('hymns.id')->all();

        return response()->json(['data' => $found->map(fn (Hymn $h) => $this->card($h, in_array($h->id, $favorites, true)))->values()]);
    }

    public function favorites(Request $request): JsonResponse
    {
        $hymns = $request->user()->favoriteHymns()->published()->with('hymnal')->orderByPivot('created_at', 'desc')->get();

        return response()->json(['data' => $hymns->map(fn (Hymn $h) => $this->card($h, true))->values()]);
    }

    public function favorite(Request $request, Hymn $hymn): JsonResponse
    {
        abort_unless($hymn->status === 'published', 404);
        $request->user()->favoriteHymns()->syncWithoutDetaching([$hymn->id]);

        return $this->favoriteState($hymn, true);
    }

    public function unfavorite(Request $request, Hymn $hymn): JsonResponse
    {
        $request->user()->favoriteHymns()->detach($hymn->id);

        return $this->favoriteState($hymn, false);
    }

    protected function favoriteState(Hymn $hymn, bool $favorite): JsonResponse
    {
        $hymn->forceFill(['favorites_count' => $hymn->favoredBy()->count()])->saveQuietly();

        return response()->json(['favorite' => $favorite, 'favorites_count' => (int) $hymn->favorites_count]);
    }

    protected function matches(Hymn $h, string $q): bool
    {
        $q = trim($q);
        if (ctype_digit($q)) {
            return $h->number === (int) $q || str_contains(Str::lower($h->title), $q);
        }
        foreach (preg_split('/\s+/', Str::lower($q), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (! str_contains((string) $h->search_text, $word)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    protected function hymnalCard(Hymnal $h): array
    {
        return [
            'id' => $h->id, 'name' => $h->name, 'slug' => $h->slug, 'language' => $h->language, 'language_label' => $h->languageLabel(), 'denomination' => $h->denomination,
            'description' => $h->description, 'hymns_count' => (int) $h->hymns_count, 'source_name' => $h->source_name, 'source_url' => $h->source_url,
        ];
    }

    /** @return array<string, mixed> */
    protected function card(Hymn $h, bool $favorite, ?Hymnal $hymnal = null): array
    {
        $hymnal ??= $h->hymnal;

        return [
            'id' => $h->id, 'number' => $h->number, 'section' => $h->section, 'title' => $h->title, 'first_line' => $h->first_line, 'author' => $h->author, 'themes' => $h->themes ?? [],
            'has_audio' => (bool) $h->audio_url, 'favorite' => $favorite,
            'hymnal' => $hymnal ? ['id' => $hymnal->id, 'name' => $hymnal->name, 'language' => $hymnal->language] : null,
        ];
    }

    /** @return array<string, mixed> */
    protected function detail(Hymn $h, bool $favorite): array
    {
        return $this->card($h, $favorite) + [
            'sections' => $h->sections(),
            'section_meaning' => collect($h->hymnal?->sections ?? [])->firstWhere('name', $h->section)['meaning'] ?? null,
            'credits' => $h->creditLines(),
            'credit_note' => $h->credit_note,
            'translator' => $h->translator, 'composer' => $h->composer, 'arranger' => $h->arranger, 'tune' => $h->tune, 'meter' => $h->meter, 'key_signature' => $h->key_signature,
            'year_written' => $h->year_written, 'scripture' => $h->scripture, 'copyright_status' => $h->copyright_status,
            'copyright_label' => __(Hymn::COPYRIGHT[$h->copyright_status] ?? 'Public domain'),
            'source_url' => $h->source_url, 'audio_url' => $h->audio_url, 'sheet_music_url' => $h->sheet_music_url,
            'share_text' => $h->shareText(),
        ];
    }

    /** The hymns before and after this one in its hymnal, for the previous / next buttons. */
    protected function neighbours(Hymn $h): array
    {
        $order = Hymn::where('hymnal_id', $h->hymnal_id)->where('status', 'published')->orderBy('section_order')->orderByRaw('number is null')->orderBy('number')->orderBy('title')->pluck('id')->all();
        $at = array_search($h->id, $order, true);

        return ['prev_id' => $at !== false ? ($order[$at - 1] ?? null) : null, 'next_id' => $at !== false ? ($order[$at + 1] ?? null) : null];
    }
}
