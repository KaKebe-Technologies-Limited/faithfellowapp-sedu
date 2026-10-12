<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\Bible\BibleService;
use App\Services\Bible\BibleUnavailable;
use App\Support\Bible\Books;
use App\Support\Bible\Reference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            'highlight' => array_filter(array_map('intval', explode('-', (string) $request->query('v')))),
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

        if ($request->boolean('partial')) {
            return response()->view('member.bible._passage', ['passage' => $passage, 'translation' => $translation, 'compact' => true]);
        }

        abort_unless($passage, 404);

        return view('member.bible.passage', [
            'passage' => $passage,
            'reference' => $request->query('ref'),
            'translation' => $translation,
            'translations' => $this->bible->translations(),
        ]);
    }

    public function setTranslation(Request $request): RedirectResponse
    {
        $data = $request->validate(['translation' => ['required', Rule::in(array_keys($this->bible->translations()))]]);
        $request->user()->forceFill(['bible_translation' => $data['translation']])->save();
        session(['bible_translation' => $data['translation']]);

        return back();
    }

    protected function translation(Request $request): string
    {
        $wanted = $request->query('t');

        return $wanted && array_key_exists($wanted, $this->bible->translations())
            ? $wanted
            : $this->bible->defaultTranslation($request->user());
    }
}
