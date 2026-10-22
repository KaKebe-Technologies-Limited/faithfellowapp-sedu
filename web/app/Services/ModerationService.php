<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\ContentReport;
use App\Models\LiveChatMessage;
use App\Models\PodcastComment;
use App\Models\Post;
use App\Models\PrayerRequest;
use App\Models\Testimony;
use App\Models\User;
use App\Notifications\Care\CommunityUpdate;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Member reports → church moderators and platform moderators.
 */
class ModerationService
{
    /**
     * @param  array{reason: string, details?: ?string}  $data
     */
    public function report(User $reporter, Model $content, array $data): ContentReport
    {
        $report = ContentReport::firstOrCreate(
            ['reportable_type' => $content->getMorphClass(), 'reportable_id' => $content->getKey(), 'user_id' => $reporter->id],
            ['church_id' => $content->church_id ?? null, 'reason' => $data['reason'], 'details' => $data['details'] ?? null, 'status' => 'open'],
        );

        // Flag comments straight away so moderators see them at the top of their queue.
        if (($content instanceof Comment || $content instanceof PodcastComment) && $content->status === 'visible') {
            $content->update(['status' => 'flagged']);
        }

        if ($report->wasRecentlyCreated && $content->church_id && ($church = $content->church)) {
            $moderators = User::whereIn('id', $church->staffMembers()->with('role')->where('status', 'active')->get()
                ->filter(fn ($s) => $s->is_admin || in_array('community.moderate', $s->role?->permissions ?? [], true))->pluck('user_id'))->get();
            Notification::send($moderators, new CommunityUpdate($church, __('Content reported: :reason', ['reason' => __(ContentReport::REASONS[$report->reason])]), route('church-admin.reports.index', $church), null, 'bi-flag'));
        }

        return $report;
    }

    /**
     * Hide the reported content and close every open report about it.
     */
    public function hide(ContentReport $report, User $moderator): void
    {
        $content = $report->reportable;

        match (true) {
            $content instanceof Comment, $content instanceof PodcastComment, $content instanceof PrayerRequest => $content?->update(['status' => 'hidden']),
            $content instanceof Post => $content->update(['status' => 'hidden']),
            $content instanceof Testimony => $content->update(['status' => 'rejected']),
            $content instanceof LiveChatMessage => $content->update(['is_hidden' => true]),
            default => null,
        };

        $this->close($report, 'actioned', $moderator);
        if ($content) {
            Audit::log('moderation.hidden', $content, __('Hidden after a report (:reason)', ['reason' => $report->reason]));
        }
    }

    public function dismiss(ContentReport $report, User $moderator): void
    {
        $this->close($report, 'dismissed', $moderator);

        // A dismissed comment goes back to normal.
        if (($report->reportable instanceof Comment || $report->reportable instanceof PodcastComment) && $report->reportable->status === 'flagged') {
            $report->reportable->update(['status' => 'visible']);
        }
    }

    /** Author of a piece of reported content, if any. */
    public function author(?Model $content): ?User
    {
        return $content && isset($content->user_id) ? User::find($content->user_id) : null;
    }

    protected function close(ContentReport $report, string $status, User $moderator): void
    {
        ContentReport::where('reportable_type', $report->reportable_type)->where('reportable_id', $report->reportable_id)
            ->where('status', 'open')->update(['status' => $status, 'handled_by' => $moderator->id, 'handled_at' => now()]);
    }
}
