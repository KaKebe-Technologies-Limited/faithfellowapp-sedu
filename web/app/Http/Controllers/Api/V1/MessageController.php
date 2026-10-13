<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DirectMessage;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Private chat between connected members. The app polls a thread every few seconds with `after=<last id>`.
 */
class MessageController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    /** The conversation list: the other person, the last message and how many are unread. */
    public function index(Request $request): JsonResponse
    {
        $me = $request->user()->id;
        $blocked = UserBlock::idsAround($me);

        $rows = DirectMessage::query()
            ->selectRaw('CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END AS partner_id, MAX(id) AS last_id, SUM(recipient_id = ? AND read_at IS NULL) AS unread', [$me, $me])
            ->where(fn ($q) => $q->where('sender_id', $me)->orWhere('recipient_id', $me))
            ->groupBy('partner_id')->orderByDesc('last_id')->get()
            ->reject(fn ($r) => in_array((int) $r->partner_id, $blocked, true));

        $partners = User::with('media')->whereIn('id', $rows->pluck('partner_id'))->get()->keyBy('id');
        $last = DirectMessage::whereIn('id', $rows->pluck('last_id'))->get()->keyBy('id');

        return response()->json(['data' => $rows->filter(fn ($r) => $partners->has((int) $r->partner_id))->map(fn ($r) => [
            'user' => ['id' => $partners[$r->partner_id]->id, 'name' => $partners[$r->partner_id]->name, 'avatar_url' => $partners[$r->partner_id]->avatar_url],
            'last' => ['body' => $last[$r->last_id]->body, 'at' => $last[$r->last_id]->created_at?->toIso8601String(), 'mine' => $last[$r->last_id]->sender_id === $me],
            'unread' => (int) $r->unread,
        ])->values()]);
    }

    public function unread(Request $request): JsonResponse
    {
        return response()->json(['unread' => DirectMessage::where('recipient_id', $request->user()->id)->whereNull('read_at')
            ->whereNotIn('sender_id', UserBlock::idsAround($request->user()->id) ?: [0])->count()]);
    }

    /** A conversation, oldest first. `after=<id>` returns only newer messages; `before=<id>` loads older ones. */
    public function thread(Request $request, User $user): JsonResponse
    {
        $me = $request->user();
        abort_unless($this->people->mayMessage($me, $user) || DirectMessage::between($me->id, $user->id)->exists() && ! in_array($user->id, UserBlock::idsAround($me->id), true), 404);

        $query = DirectMessage::between($me->id, $user->id);
        $messages = $request->filled('after')
            ? $query->where('id', '>', (int) $request->query('after'))->orderBy('id')->take(100)->get()
            : $query->when($request->filled('before'), fn ($q) => $q->where('id', '<', (int) $request->query('before')))->orderByDesc('id')->take(40)->get()->reverse()->values();

        DirectMessage::where('sender_id', $user->id)->where('recipient_id', $me->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'data' => $messages->map(fn (DirectMessage $m) => $this->payload($m, $me))->values(),
            'partner' => ['id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->avatar_url, 'can_message' => $this->people->mayMessage($me, $user)],
            'has_more' => ! $request->filled('after') && $messages->count() === 40,
        ]);
    }

    public function send(Request $request, User $user): JsonResponse
    {
        $me = $request->user();
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        abort_unless($this->people->mayMessage($me, $user), 403, __('You can only message people you are connected to.'));

        $message = DirectMessage::create(['sender_id' => $me->id, 'recipient_id' => $user->id, 'body' => trim(strip_tags($data['body']))]);

        return response()->json(['data' => $this->payload($message, $me)], 201);
    }

    /** @return array<string, mixed> */
    protected function payload(DirectMessage $m, User $me): array
    {
        return ['id' => $m->id, 'body' => $m->body, 'mine' => $m->sender_id === $me->id, 'at' => $m->created_at?->toIso8601String(), 'read' => $m->read_at !== null];
    }
}
