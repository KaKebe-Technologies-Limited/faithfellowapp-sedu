<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Meeting people of the same faith: discover members (filters for country, city, age, interests), see a profile,
 * ask to connect, answer requests, disconnect and block. Chatting is in MessageController.
 */
class PeopleController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['church', 'everyone'])],
            'church' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'interest' => ['nullable', 'string', 'max:40'],
            'age_min' => ['nullable', 'integer', 'min:18', 'max:100'],
            'age_max' => ['nullable', 'integer', 'min:18', 'max:100'],
        ]);

        $page = $this->people->discover($request->user(), $filters)->paginate(20)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (User $u) => $this->people->person($request->user(), $u))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'viewer' => ['can_connect' => $this->people->isAdult($request->user()), 'discoverable' => (bool) ($request->user()->directory_opt_in || $request->user()->open_to_connect)],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($this->people->canSee($request->user(), $user), 404);

        return response()->json(['data' => $this->people->person($request->user(), $user)]);
    }

    public function connect(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:280']]);
        $connection = $this->people->request($request->user(), $user, $data['message'] ?? null);

        return response()->json(['data' => $this->people->person($request->user(), $user), 'status' => $connection->status], 201);
    }

    /** Your connections: people you are connected to, requests waiting for you, and requests you sent. */
    public function connections(Request $request): JsonResponse
    {
        $me = $request->user();
        $blocked = UserBlock::idsAround($me->id);
        $all = Connection::involving($me->id)->whereIn('status', ['pending', 'accepted'])->with(['requester.media', 'addressee.media'])->latest('updated_at')->get()
            ->reject(fn (Connection $c) => in_array($c->otherThan($me), $blocked, true));

        $card = fn (Connection $c) => ['connection_id' => $c->id, 'message' => $c->message, 'at' => $c->updated_at?->toIso8601String()] + $this->people->person($me, $c->requester_id === $me->id ? $c->addressee : $c->requester);

        return response()->json(['data' => [
            'friends' => $all->where('status', 'accepted')->map($card)->values(),
            'incoming' => $all->where('status', 'pending')->where('addressee_id', $me->id)->map($card)->values(),
            'outgoing' => $all->where('status', 'pending')->where('requester_id', $me->id)->map($card)->values(),
        ]]);
    }

    public function respond(Request $request, Connection $connection): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['accept', 'decline'])]]);
        $this->people->respond($request->user(), $connection, $data['action'] === 'accept');

        return response()->json(['status' => $connection->status]);
    }

    /** Cancel a request you sent, or remove a connection. */
    public function disconnect(Request $request, User $user): JsonResponse
    {
        $this->people->disconnect($request->user(), $user);

        return response()->json(['relation' => 'none']);
    }

    public function block(Request $request, User $user): JsonResponse
    {
        $this->people->block($request->user(), $user);

        return response()->json(['blocked' => true]);
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $this->people->unblock($request->user(), $user);

        return response()->json(['blocked' => false]);
    }

    public function blocked(Request $request): JsonResponse
    {
        return response()->json(['data' => UserBlock::where('user_id', $request->user()->id)->with('blocked.media')->get()
            ->map(fn ($b) => ['id' => $b->blocked->id, 'name' => $b->blocked->name, 'avatar_url' => $b->blocked->avatar_url])->values()]);
    }
}
