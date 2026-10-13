<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BibleChapter;
use App\Models\BibleHighlight;
use App\Services\Bible\BibleService;
use App\Services\Bible\BibleUnavailable;
use App\Support\Bible\Books;
use App\Support\Bible\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Bible in the app: translations and books, a chapter with the member's highlights, passages
 * ("Matthew 3-5") for plans and sermons, highlighting, and the member's translation choice.
 */
class BibleController extends Controller
{
    public function __construct(protected BibleService $bible) {}

    /** Everything the Bible home needs: translations, the 66 books and the latest highlights. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'translations' => $this->translations(),
            'translation' => $this->bible->defaultTranslation($user),
            'books' => collect(Books::ALL)->map(fn ($b, $code) => ['code' => $code, 'name' => $b[0], 'chapters' => $b[1], 'testament' => $b[2], 'aliases' => $b[3]])->values(),
            'colors' => collect(BibleHighlight::COLORS)->map(fn ($c, $key) => ['key' => $key, 'label' => $c[0], 'hex' => $c[1]])->values(),
            'highlights' => $this->recentHighlights($request),
        ]]);
    }

    public function read(Request $request, string $book, int $chapter): JsonResponse
    {
        $book = strtoupper($book);
        abort_unless(Books::exists($book) && $chapter >= 1 && $chapter <= Books::chapters($book), 404);
        $translation = $this->translation($request);

        try {
            $verses = $this->bible->chapter($translation, $book, $chapter);
        } catch (BibleUnavailable) {
            return response()->json(['message' => __('We could not load this chapter right now. Check your connection and try again.')], 503);
        }

        $colors = BibleHighlight::mapFor($request->user(), [['book' => $book, 'chapter' => $chapter]]);
        $previous = $chapter > 1 ? [$book, $chapter - 1] : (($p = Books::previous($book)) ? [$p, Books::chapters($p)] : null);
        $next = $chapter < Books::chapters($book) ? [$book, $chapter + 1] : (($n = Books::next($book)) ? [$n, 1] : null);

        return response()->json(['data' => [
            'book' => $book,
            'book_name' => Books::name($book),
            'chapter' => $chapter,
            'chapters' => Books::chapters($book),
            'translation' => $translation,
            'verses' => $this->withColors($verses, $book, $chapter, $colors),
            'previous' => $previous ? ['book' => $previous[0], 'chapter' => $previous[1], 'label' => Books::name($previous[0]).' '.$previous[1]] : null,
            'next' => $next ? ['book' => $next[0], 'chapter' => $next[1], 'label' => Books::name($next[0]).' '.$next[1]] : null,
        ]]);
    }

    /**
     * A whole book's text for reading offline. The app asks again and again until nothing is missing: each answer
     * holds the chapters it does not have yet (`have` lists the ones it already stores), and the server fetches a few
     * more from the Bible provider each time, so a big book is ready after some requests instead of one very long one.
     */
    public function download(Request $request, string $book): JsonResponse
    {
        $book = strtoupper($book);
        abort_unless(Books::exists($book), 404);
        if (PHP_SAPI !== 'cli') {
            set_time_limit(60); // a slow Bible provider can take a while; never touch the limit of the test runner or console
        }
        $translation = $this->translation($request);
        $total = Books::chapters($book);
        $have = collect(explode(',', (string) $request->query('have')))->map(fn ($n) => (int) $n)->filter()->flip();

        $cached = BibleChapter::where('translation', $translation)->where('book', $book)->pluck('verses', 'chapter');
        $out = $cached->reject(fn ($verses, $chapter) => $have->has((int) $chapter))->all();

        // Top up what the server does not hold yet, a few chapters per request and no longer than about 20 seconds.
        $held = $cached->keys()->map(fn ($c) => (int) $c)->all();
        $fetched = 0;
        $deadline = microtime(true) + 20;
        foreach (array_diff(range(1, $total), $held) as $chapter) {
            if ($fetched >= 5 || microtime(true) > $deadline) {
                break;
            }
            try {
                $out[$chapter] = $this->bible->chapter($translation, $book, $chapter);
                $held[] = $chapter;
                $fetched++;
            } catch (BibleUnavailable) {
                break;
            }
        }

        return response()->json(['data' => [
            'translation' => $translation,
            'book' => $book,
            'total' => $total,
            'chapters' => (object) collect($out)->map(fn ($verses) => array_map(fn ($v) => ['v' => $v['v'], 't' => $v['t']], $verses))->all(),
            'missing' => array_values(array_diff(range(1, $total), $held)),
            'fetched' => $fetched,
        ]]);
    }

    /** A reference such as "John 3:16" or "Matthew 3-5": `ref` is required, `t` picks the translation. */
    public function passage(Request $request): JsonResponse
    {
        $request->validate(['ref' => ['required', 'string', 'max:200']]);
        $translation = $this->translation($request);
        $passage = $this->bible->passage($request->query('ref'), $translation);
        abort_unless($passage, 404, __('We could not find “:q”. Try e.g. John 3:16 or Psalm 23.', ['q' => $request->query('ref')]));

        $colors = BibleHighlight::mapFor($request->user(), $passage['sections']);

        return response()->json(['data' => [
            'title' => $passage['title'],
            'translation' => $translation,
            'sections' => collect($passage['sections'])->map(fn ($s) => [
                'label' => $s['label'], 'book' => $s['book'], 'chapter' => $s['chapter'], 'error' => $s['error'] ?? null,
                'verses' => $this->withColors($s['verses'], $s['book'], $s['chapter'], $colors),
            ])->values(),
        ]]);
    }

    /** Body: { verses: ["JHN.3.16", …], color: "yellow" | null } — null clears the highlight. */
    public function highlight(Request $request): JsonResponse
    {
        $data = $request->validate([
            'verses' => ['required', 'array', 'min:1', 'max:200'],
            'verses.*' => ['string', 'regex:/^[1-3A-Z]{3}\.\d{1,3}\.\d{1,3}$/'],
            'color' => ['nullable', Rule::in(array_keys(BibleHighlight::COLORS))],
        ]);

        $user = $request->user();
        $keys = collect($data['verses'])->unique()->map(fn ($key) => array_combine(['book', 'chapter', 'verse'], explode('.', $key)))
            ->filter(fn ($v) => Books::exists($v['book']) && (int) $v['chapter'] >= 1 && (int) $v['chapter'] <= Books::chapters($v['book']) && (int) $v['verse'] >= 1);

        foreach ($keys as $v) {
            $match = ['user_id' => $user->id, 'book' => $v['book'], 'chapter' => (int) $v['chapter'], 'verse' => (int) $v['verse']];
            $data['color']
                ? BibleHighlight::updateOrCreate($match, ['color' => $data['color']])
                : BibleHighlight::where($match)->delete();
        }

        return response()->json(['color' => $data['color'], 'count' => $keys->count()]);
    }

    /** All of the member's highlights, newest first (verse text only where the chapter is already cached). */
    public function highlights(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->recentHighlights($request, 200)]);
    }

    public function setTranslation(Request $request): JsonResponse
    {
        $data = $request->validate(['translation' => ['required', Rule::in(array_keys($this->bible->translations()))]]);
        $request->user()->forceFill(['bible_translation' => $data['translation']])->save();

        return response()->json(['translation' => $data['translation']]);
    }

    /** @return array<int, array{key: string, name: string, short: string, language: string}> */
    protected function translations(): array
    {
        return collect($this->bible->translations())->map(fn ($t) => ['key' => $t['key'], 'name' => $t['name'], 'short' => $t['short'], 'language' => $t['language']])->values()->all();
    }

    /**
     * @param  array<int, array{v: int, t: string}>  $verses
     * @param  array<string, string>  $colors
     * @return array<int, array{v: int, t: string, color: ?string}>
     */
    protected function withColors(array $verses, string $book, int $chapter, array $colors): array
    {
        return array_map(fn ($v) => ['v' => $v['v'], 't' => $v['t'], 'color' => $colors["$book.$chapter.{$v['v']}"] ?? null], $verses);
    }

    /** @return array<int, array<string, mixed>> */
    protected function recentHighlights(Request $request, int $limit = 6): array
    {
        $highlights = BibleHighlight::where('user_id', $request->user()->id)->latest('updated_at')->take($limit)->get();
        if ($highlights->isEmpty()) {
            return [];
        }
        $translation = $this->bible->defaultTranslation($request->user());
        $chapters = BibleChapter::where('translation', $translation)
            ->where(fn ($q) => $highlights->each(fn ($h) => $q->orWhere(fn ($w) => $w->where('book', $h->book)->where('chapter', $h->chapter))))
            ->get()->keyBy(fn ($c) => $c->book.'.'.$c->chapter);

        return $highlights->map(fn (BibleHighlight $h) => [
            'key' => $h->key(),
            'label' => $h->label(),
            'book' => $h->book,
            'chapter' => $h->chapter,
            'verse' => $h->verse,
            'color' => $h->color,
            'text' => collect($chapters[$h->book.'.'.$h->chapter]->verses ?? [])->firstWhere('v', $h->verse)['t'] ?? null,
        ])->all();
    }

    protected function translation(Request $request): string
    {
        $wanted = $request->query('t');

        return $wanted && array_key_exists($wanted, $this->bible->translations()) ? $wanted : $this->bible->defaultTranslation($request->user());
    }
}
