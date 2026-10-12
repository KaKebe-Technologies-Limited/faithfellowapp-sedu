<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\BibleChapter;
use App\Models\BibleHighlight;
use App\Services\Bible\BibleService;
use App\Services\Bible\BibleUnavailable;
use App\Support\Bible\Books;
use App\Support\Bible\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * In-app Bible: book picker, chapter reader, passages ("Matthew 3–5") for
 * reading plans, devotionals and sermons.
 */
class BibleController extends Controller
{
    public function __construct(protected BibleService $bible) {}

    public function index(Request $request): View|RedirectResponse
    {
        // "Go to" box: a reference jumps straight to the passage.
        if ($request->filled('q')) {
            $ref = Reference::parse($request->query('q'));
            if (! $ref) {
                return back()->withErrors(['q' => __('We could not find “:q”. Try e.g. John 3:16 or Psalm 23.', ['q' => $request->query('q')])]);
            }
            $first = $ref->segments[0];

            return count($ref->segments) === 1 && ! $first['from']
                ? redirect()->route('bible.read', [$first['book'], $first['chapter']])
                : redirect()->route('bible.passage', ['ref' => $ref->title()]);
        }

        $last = session('bible_last');

        return view('member.bible.index', [
            'translations' => $this->bible->translations(),
            'translation' => $this->bible->defaultTranslation($request->user()),
            'last' => $last && Books::exists($last['book'] ?? '') ? $last : null,
            'books' => collect(Books::ALL)->map(fn ($b, $code) => ['code' => $code, 'name' => $b[0], 'chapters' => $b[1], 'testament' => $b[2]])->groupBy('testament'),
            'recentHighlights' => $this->recentHighlights($request),
        ]);
    }

    public function read(Request $request, string $book, int $chapter): View
    {
        $book = strtoupper($book);
        abort_unless(Books::exists($book) && $chapter >= 1 && $chapter <= Books::chapters($book), 404);
        $translation = $this->translation($request);

        try {
            $verses = $this->bible->chapter($translation, $book, $chapter);
            $error = null;
        } catch (BibleUnavailable) {
            $verses = [];
            $error = __('We could not load this chapter right now. Check your connection and try again.');
        }

        session(['bible_last' => ['book' => $book, 'chapter' => $chapter]]);

        $previous = $chapter > 1 ? [$book, $chapter - 1] : (($p = Books::previous($book)) ? [$p, Books::chapters($p)] : null);
        $next = $chapter < Books::chapters($book) ? [$book, $chapter + 1] : (($n = Books::next($book)) ? [$n, 1] : null);

        return view('member.bible.read', [
            'book' => $book,
            'bookName' => Books::name($book),
            'chapter' => $chapter,
            'chapters' => Books::chapters($book),
            'verses' => $verses,
            'error' => $error,
            'translation' => $translation,
            'translations' => $this->bible->translations(),
            'previous' => $previous,
            'next' => $next,
            'target' => array_filter(array_map('intval', explode('-', (string) $request->query('v')))),
            'highlights' => BibleHighlight::mapFor($request->user(), [['book' => $book, 'chapter' => $chapter]]),
        ]);
    }

    /**
     * A multi-chapter passage. With ?partial=1 returns just the text block
     * (loaded into reading-plan days without slowing the page).
     */
    public function passage(Request $request): View|Response
    {
        $request->validate(['ref' => ['required', 'string', 'max:200']]);
        $translation = $this->translation($request);
        $passage = $this->bible->passage($request->query('ref'), $translation);

        $highlights = $passage ? BibleHighlight::mapFor($request->user(), $passage['sections']) : [];

        if ($request->boolean('partial')) {
            return response()->view('member.bible._passage', ['passage' => $passage, 'translation' => $translation, 'translations' => $this->bible->translations(), 'highlights' => $highlights, 'compact' => true]);
        }

        abort_unless($passage, 404);

        return view('member.bible.passage', [
            'passage' => $passage,
            'reference' => $request->query('ref'),
            'translation' => $translation,
            'translations' => $this->bible->translations(),
            'highlights' => $highlights,
        ]);
    }

    /**
     * Colour (or clear) verses. Body: { verses: ["JHN.3.16", …], color: "yellow" | null }.
     */
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

    public function setTranslation(Request $request): RedirectResponse
    {
        $data = $request->validate(['translation' => ['required', Rule::in(array_keys($this->bible->translations()))]]);
        $request->user()->forceFill(['bible_translation' => $data['translation']])->save();
        session(['bible_translation' => $data['translation']]);

        return back();
    }

    /**
     * The member's latest highlights with verse text, read from the chapter
     * cache only (never calls the Bible API just to fill this list).
     *
     * @return Collection<int, array{label: string, text: ?string, color: string, url: string}>
     */
    protected function recentHighlights(Request $request)
    {
        $highlights = BibleHighlight::where('user_id', $request->user()->id)->latest('updated_at')->take(6)->get();
        if ($highlights->isEmpty()) {
            return collect();
        }
        $translation = $this->bible->defaultTranslation($request->user());
        $chapters = BibleChapter::where('translation', $translation)
            ->where(fn ($q) => $highlights->each(fn ($h) => $q->orWhere(fn ($w) => $w->where('book', $h->book)->where('chapter', $h->chapter))))
            ->get()->keyBy(fn ($c) => $c->book.'.'.$c->chapter);

        return $highlights->map(fn (BibleHighlight $h) => [
            'label' => $h->label(),
            'text' => collect($chapters[$h->book.'.'.$h->chapter]->verses ?? [])->firstWhere('v', $h->verse)['t'] ?? null,
            'color' => $h->color,
            'url' => route('bible.read', [$h->book, $h->chapter, 'v' => $h->verse]).'#v'.$h->verse,
        ]);
    }

    protected function translation(Request $request): string
    {
        $wanted = $request->query('t');

        return $wanted && array_key_exists($wanted, $this->bible->translations())
            ? $wanted
            : $this->bible->defaultTranslation($request->user());
    }
}
