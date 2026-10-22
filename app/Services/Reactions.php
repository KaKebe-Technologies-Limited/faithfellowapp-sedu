<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Campaign;
use App\Models\CampaignUpdate;
use App\Models\Post;
use App\Models\PrayerRequest;
use App\Models\Reaction;
use App\Models\Sermon;
use App\Models\Testimony;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Reactions (like, love, smile, amen, pray, celebrate) on church content. One reaction per person per item: pressing another replaces it, pressing the
 * same one again takes it back. Shared by the website and the app.
 */
class Reactions
{
    /** Content members can react to and comment on. */
    public const CONTENT = [
        'post' => Post::class,
        'sermon' => Sermon::class,
        'testimony' => Testimony::class,
        'campaign' => Campaign::class,
        'campaign_update' => CampaignUpdate::class,
        'prayer' => PrayerRequest::class,
        'announcement' => Announcement::class,
    ];

    /** The item, or a 404 / 403 when there is no such item or this person may not react to it. */
    public function find(string $type, int $id, User $user): Model
    {
        $class = self::CONTENT[$type] ?? abort(404);
        $model = $class::with('church')->findOrFail($id);

        $allowed = match (true) {
            $model instanceof PrayerRequest, $model instanceof Announcement => $user->can('view', $model),
            $model instanceof Testimony => $model->status === 'approved' && $model->church?->isActive(),
            default => $model->church?->isActive() && ($user->isMemberOf($model->church_id) || ($model->visibility ?? null) === 'public'),
        };
        abort_unless($allowed, 403);

        return $model;
    }

    /**
     * Presses a reaction. Answers the counts of every reaction and which one is this person's (null when they took theirs back).
     *
     * @return array{counts: array<string, int>, mine: string|null}
     */
    public function toggle(Model $model, User $user, string $type): array
    {
        $where = ['reactable_type' => $model->getMorphClass(), 'reactable_id' => $model->getKey(), 'user_id' => $user->id];
        $existing = Reaction::where($where)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();
            $mine = null;
        } else {
            Reaction::updateOrCreate($where, ['church_id' => $model->church_id, 'type' => $type]);
            $mine = $type;
        }

        return ['counts' => $this->counts($model), 'mine' => $mine];
    }

    /** @return array<string, int> */
    public function counts(Model $model): array
    {
        return Reaction::where('reactable_type', $model->getMorphClass())->where('reactable_id', $model->getKey())
            ->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type')->map(fn ($c) => (int) $c)->all();
    }

    public function mine(Model $model, ?User $user): ?string
    {
        return $user ? Reaction::where('reactable_type', $model->getMorphClass())->where('reactable_id', $model->getKey())->where('user_id', $user->id)->value('type') : null;
    }
}
