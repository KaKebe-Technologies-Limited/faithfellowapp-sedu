<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Devotional;
use App\Models\Post;
use App\Models\Sermon;
use App\Notifications\Content\AnnouncementPublished;
use App\Notifications\Content\NewChurchUpdate;
use App\Notifications\Content\NewDevotional;
use App\Notifications\Content\NewSermonPublished;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Notifies subscribers when content goes live — immediately on publish, or
 * via the scheduler (`ff:publish-scheduled`) for scheduled posts.
 * `notified_at` makes this idempotent.
 */
class ContentPublisher
{
    public function __construct(protected NotificationService $notifications) {}

    public function announceIfLive(Model $model): bool
    {
        if ($model->notified_at || ! $this->isLive($model)) {
            return false;
        }

        // Claim it first (the scheduler may be looking at the same item), and give the claim back if telling the members fails: the content is saved
        // either way, and the scheduler tries again every minute for two days.
        $claimed = $model->newQuery()->whereKey($model->getKey())->whereNull('notified_at')->update(['notified_at' => now()]);
        if (! $claimed) {
            return false;
        }
        $model->forceFill(['notified_at' => now()])->syncOriginal();

        try {
            $this->notify($model);
        } catch (Throwable $e) {
            $model->newQuery()->whereKey($model->getKey())->update(['notified_at' => null]);
            $model->forceFill(['notified_at' => null])->syncOriginal();
            report($e);

            return false;
        }

        return true;
    }

    protected function notify(Model $model): void
    {
        $model->loadMissing('church');

        match (true) {
            $model instanceof Sermon => $this->notifications->notifyChurch($model->church, new NewSermonPublished($model)),
            $model instanceof Devotional => $this->notifications->notifyChurch($model->church, new NewDevotional($model)),
            $model instanceof Post => $this->notifications->notifyChurch($model->church, new NewChurchUpdate($model), 'members', ['except_user_id' => $model->user_id]),
            $model instanceof Announcement => $this->notifications->notifyChurch(
                $model->church,
                new AnnouncementPublished($model),
                match ($model->audience) {
                    'staff' => 'staff',
                    'branch' => 'branch',
                    'group' => 'group',
                    default => 'members',
                },
                ['branch_id' => $model->branch_id, 'small_group_id' => $model->small_group_id],
            ),
            default => null,
        };
    }

    /**
     * Called every minute by the scheduler.
     */
    public function publishDue(): int
    {
        $count = 0;

        foreach ([Sermon::class, Announcement::class, Post::class] as $class) {
            $class::query()->with('church')->published()->whereNull('notified_at')
                ->where('published_at', '>=', now()->subDays(2))
                ->each(function ($model) use (&$count) {
                    if ($model->status === 'scheduled') {
                        $model->forceFill(['status' => 'published'])->saveQuietly();
                    }
                    $count += (int) $this->announceIfLive($model);
                });
        }

        Devotional::query()->with('church')->published()->whereNull('notified_at')->whereDate('publish_date', today())
            ->each(function ($model) use (&$count) {
                $count += (int) $this->announceIfLive($model);
            });

        return $count;
    }

    protected function isLive(Model $model): bool
    {
        return match (true) {
            $model instanceof Devotional => $model->status === 'published' && $model->publish_date->isToday(),
            $model instanceof Post => $model->status === 'published' && $model->published_at?->lte(now()) && $model->church?->isActive(),
            default => $model->isPublished() && $model->church?->isActive(),
        };
    }
}
