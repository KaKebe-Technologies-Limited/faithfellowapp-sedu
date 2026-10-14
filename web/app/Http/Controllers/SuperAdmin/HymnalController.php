<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Hymn;
use App\Models\Hymnal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The hymn book, managed by the super admin only: hymnals (collections, each in one language) and the hymns in them.
 * Every hymn carries its credits (who wrote the words and the music, the licence and where the text was collected).
 * Members read hymns in the app; they cannot add or change them.
 */
class HymnalController extends Controller
{
    public function index(): View
    {
        return view('super-admin.hymns.index', ['hymnals' => Hymnal::orderBy('sort')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('super-admin.hymns.hymnal-form', ['hymnal' => new Hymnal(['language' => 'en', 'is_published' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $hymnal = Hymnal::create($this->validatedHymnal($request));

        return redirect()->route('super-admin.hymnals.show', $hymnal)->with('success', __('The hymnal was created. Add its hymns now.'));
    }

    public function show(Request $request, Hymnal $hymnal): View
    {
        $q = trim((string) $request->query('q'));
        $hymns = $hymnal->hymns()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('author', 'like', "%{$q}%")->orWhere('number', ctype_digit($q) ? (int) $q : -1)))
            ->orderByRaw('number is null')->orderBy('number')->orderBy('title')->paginate(40)->withQueryString();

        return view('super-admin.hymns.show', ['hymnal' => $hymnal, 'hymns' => $hymns, 'q' => $q]);
    }

    public function edit(Hymnal $hymnal): View
    {
        return view('super-admin.hymns.hymnal-form', ['hymnal' => $hymnal]);
    }

    public function update(Request $request, Hymnal $hymnal): RedirectResponse
    {
        $hymnal->update($this->validatedHymnal($request, $hymnal));

        return redirect()->route('super-admin.hymnals.show', $hymnal)->with('success', __('Saved.'));
    }

    public function destroy(Hymnal $hymnal): RedirectResponse
    {
        $hymnal->delete();

        return redirect()->route('super-admin.hymnals.index')->with('success', __('The hymnal was deleted.'));
    }

    // ── Hymns ───────────────────────────────────────────────────────────────────────────────────────────

    public function createHymn(Hymnal $hymnal): View
    {
        return view('super-admin.hymns.hymn-form', ['hymnal' => $hymnal, 'hymn' => new Hymn(['number' => ((int) $hymnal->hymns()->max('number')) + 1, 'copyright_status' => 'public_domain', 'status' => 'published']), 'lyrics' => '']);
    }

    public function storeHymn(Request $request, Hymnal $hymnal): RedirectResponse
    {
        $hymn = $hymnal->hymns()->create($this->validatedHymn($request, $hymnal));

        return redirect()->route('super-admin.hymnals.show', $hymnal)->with('success', __('“:title” was added.', ['title' => $hymn->title]));
    }

    public function editHymn(Hymnal $hymnal, Hymn $hymn): View
    {
        abort_unless($hymn->hymnal_id === $hymnal->id, 404);

        return view('super-admin.hymns.hymn-form', ['hymnal' => $hymnal, 'hymn' => $hymn, 'lyrics' => $hymn->lyricsForEditing()]);
    }

    public function updateHymn(Request $request, Hymnal $hymnal, Hymn $hymn): RedirectResponse
    {
        abort_unless($hymn->hymnal_id === $hymnal->id, 404);
        $hymn->update($this->validatedHymn($request, $hymnal, $hymn));

        return redirect()->route('super-admin.hymnals.show', $hymnal)->with('success', __('Saved.'));
    }

    public function destroyHymn(Hymnal $hymnal, Hymn $hymn): RedirectResponse
    {
        abort_unless($hymn->hymnal_id === $hymnal->id, 404);
        $hymn->delete();

        return redirect()->route('super-admin.hymnals.show', $hymnal)->with('success', __('The hymn was deleted.'));
    }

    // ── Validation ──────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    protected function validatedHymnal(Request $request, ?Hymnal $hymnal = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'language' => ['required', Rule::in(array_keys(Hymnal::LANGUAGES))],
            'denomination' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'source_name' => ['nullable', 'string', 'max:160'],
            'source_url' => ['nullable', 'url:https,http', 'max:300'],
            'sort' => ['nullable', 'integer', 'between:0,999'],
        ]);
        $data['is_published'] = $request->boolean('is_published');
        $data['sort'] = (int) ($data['sort'] ?? 0);

        return $data;
    }

    /** @return array<string, mixed> */
    protected function validatedHymn(Request $request, Hymnal $hymnal, ?Hymn $hymn = null): array
    {
        $data = $request->validate([
            'number' => ['nullable', 'integer', 'between:1,9999', Rule::unique('hymns', 'number')->where('hymnal_id', $hymnal->id)->whereNull('deleted_at')->ignore($hymn?->id)],
            'title' => ['required', 'string', 'max:200'],
            'lyrics' => ['required', 'string', 'max:20000'],
            'author' => ['required', 'string', 'max:255'],
            'translator' => ['nullable', 'string', 'max:255'],
            'composer' => ['nullable', 'string', 'max:255'],
            'arranger' => ['nullable', 'string', 'max:255'],
            'tune' => ['nullable', 'string', 'max:120'],
            'meter' => ['nullable', 'string', 'max:60'],
            'key_signature' => ['nullable', 'string', 'max:8'],
            'year_written' => ['nullable', 'integer', 'between:100,2100'],
            'themes' => ['nullable', 'string', 'max:300'],
            'scripture' => ['nullable', 'string', 'max:200'],
            'copyright_status' => ['required', Rule::in(array_keys(Hymn::COPYRIGHT))],
            'credit_note' => ['nullable', 'string', 'max:1500'],
            'source_url' => ['nullable', 'url:https,http', 'max:400'],
            'audio_url' => ['nullable', 'url:https', 'max:500'],
            'sheet_music_url' => ['nullable', 'url:https', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ], [
            'author.required' => __('Credit the author of the words (write “Unknown” or “Traditional” when it is not known).'),
            'audio_url.url' => __('The audio link must start with https.'),
        ]);

        $lyrics = Hymn::parseLyrics($data['lyrics']);
        if ($lyrics['verses'] === []) {
            throw ValidationException::withMessages(['lyrics' => __('Write at least one verse. Separate verses with an empty line.')]);
        }
        unset($data['lyrics']);

        $data['themes'] = collect(explode(',', (string) ($data['themes'] ?? '')))->map(fn ($t) => trim($t))->filter()->unique()->take(8)->values()->all() ?: null;

        return $data + ['verses' => $lyrics['verses'], 'refrain' => $lyrics['refrain'], 'first_line' => null];
    }
}
