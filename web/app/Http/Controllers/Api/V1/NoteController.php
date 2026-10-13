<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The member's private notebook in the app. A note belongs to a "context" (one per thing being read:
 * `bible:JHN.3`, `sermon:12`, `devotional:7`, `plan:4:2`, or a free `note:…`), so writing about the
 * same passage again continues the same note.
 */
class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notes = Note::ownedBy($request->user())
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%'.$request->query('q').'%')
                ->orWhere('body', 'like', '%'.$request->query('q').'%')
                ->orWhere('reference', 'like', '%'.$request->query('q').'%')))
            ->when($request->filled('kind') && array_key_exists($request->query('kind'), Note::KINDS), fn ($q) => $q->where('context', 'like', $request->query('kind').':%'))
            ->orderByDesc('is_pinned')->latest('updated_at')
            ->paginate(24)->withQueryString();

        return response()->json([
            'data' => collect($notes->items())->map(fn (Note $n) => $this->payload($n))->values(),
            'meta' => ['current_page' => $notes->currentPage(), 'last_page' => $notes->lastPage(), 'total' => $notes->total()],
        ]);
    }

    /** One note by context (for the note panel beside a chapter or sermon); an empty draft when there is none yet. */
    public function context(Request $request): JsonResponse
    {
        $request->validate(['context' => ['required', 'string', 'max:120']]);
        $note = Note::ownedBy($request->user())->where('context', $request->query('context'))->first();

        return response()->json(['data' => $note ? $this->payload($note) : null]);
    }

    /** Create or update by context. Without a context a new free-standing note is started. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'context' => ['nullable', 'string', 'max:120', 'regex:/^[a-z]+:[A-Za-z0-9:._-]+$/'],
            'reference' => ['nullable', 'string', 'max:200'],
            'church_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:50000'],
            'color' => ['nullable', Rule::in(array_keys(Note::COLORS))],
        ]);

        $note = Note::withTrashed()->firstOrNew(['user_id' => $request->user()->id, 'context' => $data['context'] ?? 'note:'.Str::lower(Str::random(12))]);
        $created = ! $note->exists;
        $note->fill(array_filter([
            'reference' => $data['reference'] ?? null,
            'church_id' => isset($data['church_id']) && $request->user()->isMemberOf((int) $data['church_id']) ? (int) $data['church_id'] : null,
            'color' => $data['color'] ?? null,
        ], fn ($v) => $v !== null) + ['title' => $data['title'] ?? $note->title, 'body' => $data['body'] ?? (string) $note->body]);
        $note->deleted_at = null;
        $note->save();

        return response()->json(['data' => $this->payload($note)], $created ? 201 : 200);
    }

    public function show(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->user_id === $request->user()->id, 404);

        return response()->json(['data' => $this->payload($note)]);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->user_id === $request->user()->id, 404);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:50000'],
            'color' => ['nullable', Rule::in(array_keys(Note::COLORS))],
        ]);
        $note->fill($data)->save();

        return response()->json(['data' => $this->payload($note)]);
    }

    public function pin(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->user_id === $request->user()->id, 404);
        $note->update(['is_pinned' => ! $note->is_pinned]);

        return response()->json(['data' => $this->payload($note)]);
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->user_id === $request->user()->id, 404);
        $note->delete();

        return response()->json(['deleted' => true]);
    }

    /** @return array<string, mixed> */
    protected function payload(Note $note): array
    {
        return [
            'id' => $note->id,
            'context' => $note->context,
            'kind' => $note->kind(),
            'kind_label' => Note::KINDS[$note->kind()][0],
            'title' => $note->title,
            'display_title' => $note->displayTitle(),
            'reference' => $note->reference,
            'body' => (string) $note->body,
            'color' => $note->color ?: 'paper',
            'color_hex' => Note::COLORS[$note->color ?: 'paper'] ?? Note::COLORS['paper'],
            'pinned' => (bool) $note->is_pinned,
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }
}
