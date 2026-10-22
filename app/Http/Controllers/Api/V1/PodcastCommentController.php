<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\PodcastComment;
use App\Models\PodcastEpisode;
use App\Models\RadioStation;
use App\Models\User;
use App\Rules\NoContactDetails;
use App\Services\ModerationService;
use App\Services\RadioService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * What listeners say about a podcast episode or a podcast channel of the radio. Anyone who can hear it can read and write; the author, the podcast's
 * maker (or a church's staff, for its own channel) and the super admin can remove a comment; anyone can report one, and a reported comment is held back
 * until a moderator has looked at it. No phone numbers, emails or links (the same rule as everywhere else in the app).
 */
class PodcastCommentController extends Controller
{
    public function __construct(protected RadioService $radio, protected ModerationService $moderation) {}

    public function episode(Request $request, PodcastEpisode $episode): JsonResponse
    {
        return $this->list($request, $this->hearable($episode));
    }

    public function addToEpisode(Request $request, PodcastEpisode $episode): JsonResponse
    {
        return $this->add($request, $this->hearable($episode));
    }

    public function station(Request $request, string $slug): JsonResponse
    {
        return $this->list($request, $this->channel($request, $slug));
    }

    public function addToStation(Request $request, string $slug): JsonResponse
    {
        return $this->add($request, $this->channel($request, $slug));
    }

    public function destroy(Request $request, PodcastComment $podcastComment): JsonResponse
    {
        abort_unless($this->mayRemove($request->user(), $podcastComment), 403);
        $podcastComment->delete();

        return response()->json(['deleted' => true]);
    }

    public function report(Request $request, PodcastComment $podcastComment): JsonResponse
    {
        abort_if($podcastComment->user_id === $request->user()->id, 422, __('You cannot report your own comment.'));
        $data = $request->validate(['reason' => ['required', Rule::in(array_keys(ContentReport::REASONS))], 'details' => ['nullable', 'string', 'max:500']]);
        $this->moderation->report($request->user(), $podcastComment, $data);

        return response()->json(['message' => __('Thank you. We will look at this comment.')]);
    }

    /** An episode that members can hear: published, and its podcast approved. */
    protected function hearable(PodcastEpisode $episode): PodcastEpisode
    {
        abort_unless($episode->status === 'published' && $episode->podcast?->isListed(), 404);

        return $episode;
    }

    /** A podcast channel of the radio (the Podcast radio, or a church's channel) that this person can hear. */
    protected function channel(Request $request, string $slug): RadioStation
    {
        $station = match (true) {
            ! RadioStation::ready() => null,
            $slug === RadioStation::PODCAST => RadioStation::podcast(),
            default => RadioStation::with('church')->where('slug', $slug)->whereNotNull('church_id')->first(),
        };
        abort_unless($station && $station->kind === 'talk' && $this->radio->canListen($request->user(), $station), 404);

        return $station;
    }

    protected function list(Request $request, Model $target): JsonResponse
    {
        if (! PodcastComment::ready()) {
            return response()->json(['data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1]]);
        }
        $page = PodcastComment::visible()->where('commentable_type', $target->getMorphClass())->where('commentable_id', $target->getKey())
            ->with('user')->latest('id')->paginate(min(30, max(1, (int) $request->query('per_page', 15))));

        return response()->json([
            'data' => collect($page->items())->map(fn (PodcastComment $c) => $this->payload($c, $request->user(), $target))->values(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    protected function add(Request $request, Model $target): JsonResponse
    {
        abort_unless(PodcastComment::ready(), 503, __('Comments are being set up. Please try again in a few minutes.'));
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:600', new NoContactDetails]]);
        $body = trim(strip_tags($data['body']));
        // The same words twice in a minute are one comment (a double tap).
        abort_unless(Cache::add('podcast-comment:'.$request->user()->id.':'.md5($target->getMorphClass().$target->getKey().$body), 1, 60), 422, __('You already said that.'));

        $comment = PodcastComment::create([
            'commentable_type' => $target->getMorphClass(), 'commentable_id' => $target->getKey(), 'user_id' => $request->user()->id, 'body' => $body, 'status' => 'visible',
        ]);

        return response()->json(['data' => $this->payload($comment->load('user'), $request->user(), $target)], 201);
    }

    /** @return array<string, mixed> */
    protected function payload(PodcastComment $comment, User $me, ?Model $target = null): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toIso8601String(),
            'mine' => $comment->user_id === $me->id,
            'can_delete' => $this->mayRemove($me, $comment, $target),
            'author' => ['id' => $comment->user_id, 'name' => $comment->user?->name ?? __('Deleted user'), 'avatar_url' => $comment->user?->avatar_url],
        ];
    }

    /** The author, the podcast's maker (a church's staff, for the church's own channel) and the super admin. */
    protected function mayRemove(User $user, PodcastComment $comment, ?Model $target = null): bool
    {
        if ($comment->user_id === $user->id || $user->isSuperAdmin()) {
            return true;
        }
        $target ??= $comment->commentable;

        return match (true) {
            $target instanceof PodcastEpisode => $target->podcast?->user_id === $user->id,
            $target instanceof RadioStation => $target->church_id !== null && $user->isStaffOf($target->church_id),
            default => false,
        };
    }
}
