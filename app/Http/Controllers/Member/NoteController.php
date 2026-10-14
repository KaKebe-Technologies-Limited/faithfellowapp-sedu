<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private notes. The note-taker panel saves by "context" (one note per
 * thing you're reading); the notebook page lists, searches and exports them.
 */
class NoteController extends Controller
{
    public function index(Request $request): View
    {
        $notes = Note::ownedBy($request->user())->with('church')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%'.$request->query('q').'%')
                ->orWhere('body', 'like', '%'.$request->query('q').'%')
                ->orWhere('reference', 'like', '%'.$request->query('q').'%')))
            ->when($request->filled('kind') && array_key_exists($request->query('kind'), Note::KINDS), fn ($q) => $q->where('context', 'like', $request->query('kind').':%'))
            ->orderByDesc('is_pinned')->latest('updated_at')
            ->paginate(24)->withQueryString();

        return view('member.notes.index', [
            'notes' => $notes,
            'counts' => Note::ownedBy($request->user())->selectRaw("SUBSTRING_INDEX(context, ':', 1) k, COUNT(*) c")->groupBy('k')->pluck('c', 'k'),
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        $note = Note::create(['user_id' => $request->user()->id, 'context' => 'note:'.Str::lower(Str::random(12)), 'title' => null, 'body' => '']);

        return redirect()->route('notes.show', $note);
    }

    public function show(Request $request, Note $note): View
    {
        abort_unless($note->user_id === $request->user()->id, 404);

        return view('member.notes.show', ['note' => $note]);
    }

    /**
     * Note-taker panel: load the note for a context (or an empty one).
     */
    public function context(Request $request): JsonResponse
    {
        $request->validate(['context' => ['required', 'string', 'max:120']]);
        $note = Note::ownedBy($request->user())->where('context', $request->query('context'))->first();

        return response()->json($note ? $this->payload($note) : ['id' => null, 'title' => null, 'body' => '', 'color' => 'paper', 'saved_at' => null]);
    }

    /**
     * Autosave from the panel or the notebook editor.
     */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'context' => ['required', 'string', 'max:120', 'regex:/^[a-z]+:[A-Za-z0-9:._-]+$/'],
            'reference' => ['nullable', 'string', 'max:200'],
            'url' => ['nullable', 'string', 'max:500'],
            'church_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:50000'],
            'color' => ['nullable', Rule::in(array_keys(Note::COLORS))],
        ]);

        // Only keep links back into this app.
        $url = $data['url'] ?? null;
        if ($url && ! Str::startsWith($url, [url('/'), '/'])) {
            $url = null;
        }

        $note = Note::withTrashed()->firstOrNew(['user_id' => $request->user()->id, 'context' => $data['context']]);
        $note->fill(array_filter([
            'reference' => $data['reference'] ?? null,
            'url' => $url,
            'church_id' => isset($data['church_id']) && $request->user()->isMemberOf((int) $data['church_id']) ? (int) $data['church_id'] : null,
            'color' => $data['color'] ?? null,
        ], fn ($v) => $v !== null) + ['title' => $data['title'] ?? $note->title, 'body' => $data['body'] ?? '']);
        $note->deleted_at = null;
        $note->save();

        return response()->json($this->payload($note));
    }

    public function pin(Request $request, Note $note): RedirectResponse
    {
        abort_unless($note->user_id === $request->user()->id, 404);
        $note->update(['is_pinned' => ! $note->is_pinned]);

        return back();
    }

    public function destroy(Request $request, Note $note): RedirectResponse
    {
        abort_unless($note->user_id === $request->user()->id, 404);
        $note->delete();

        return redirect()->route('notes.index')->with('success', __('Note deleted.'));
    }

    /**
     * Download every note as one tidy text file.
     */
    public function export(Request $request): StreamedResponse
    {
        $notes = Note::ownedBy($request->user())->orderByDesc('is_pinned')->latest('updated_at')->get();

        return response()->streamDownload(function () use ($notes) {
            echo 'My FaithFellows notes — exported '.now()->format('j F Y')."\n".str_repeat('=', 48)."\n\n";
            foreach ($notes as $note) {
                echo $note->displayTitle()."\n";
                if ($note->reference && $note->reference !== $note->displayTitle()) {
                    echo $note->reference."\n";
                }
                echo $note->updated_at->format('j M Y, H:i')."\n".str_repeat('-', 32)."\n";
                echo trim((string) $note->body)."\n\n\n";
            }
        }, 'faithfellows-notes-'.now()->format('Ymd').'.txt', ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Note $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'body' => (string) $note->body,
            'color' => $note->color,
            'saved_at' => $note->updated_at?->toIso8601String(),
            'url' => route('notes.show', $note),
        ];
    }
}
