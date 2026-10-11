<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Devotional;
use App\Models\Sermon;
use App\Notifications\Content\AnnouncementPublished;
use App\Notifications\Content\NewDevotional;
use App\Notifications\Content\NewSermonPublished;
use Illuminate\Database\Eloquent\Model;

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

        $model->forceFill(['notified_at' => now()])->saveQuietly();
        $model->loadMissing('church');

        match (true) {
            $model instanceof Sermon => $this->notifications->notifyChurch($model->church, new NewSermonPublished($model)),
            $model instanceof Devotional => $this->notifications->notifyChurch($model->church, new NewDevotional($model)),
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

        return true;
    }

    /**
     * Called every minute by the scheduler.
     */
    public function publishDue(): int
    {
        $count = 0;

        foreach ([Sermon::class, Announcement::class] as $class) {
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
            default => $model->isPublished() && $model->church?->isActive(),
        };
    }
}
