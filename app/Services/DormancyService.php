<?php

namespace App\Services;

use App\Models\Church;
use App\Models\PlatformSetting;
use App\Notifications\Church\ChurchBlockedForInactivity;
use App\Notifications\Church\ChurchQuiet;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Churches that sign up and then go quiet. A church is dormant when, for a number of days, none of its staff signed in, nothing was published (an
 * announcement, sermon, event, devotional, post, live service, campaign, message or reading plan), nobody followed it and nothing was given. It first
 * gets a warning by email and in the app; if it is still quiet after the warning period it is blocked (its page and its access in the app pause), and
 * a super admin can open it again. The two periods are set by the super admin (Settings), 30 and 14 days by default.
 */
class DormancyService
{
    /** Tables with a church_id and a created_at that show a church is being looked after by its staff. */
    private const CONTENT = ['sermons', 'events', 'announcements', 'devotionals', 'posts', 'live_streams', 'campaigns', 'church_broadcasts', 'quizzes', 'reading_plans', 'small_groups'];

    public function warnAfterDays(): int
    {
        return max(7, (int) (rescue(fn () => PlatformSetting::get('dormancy_warn_days'), null, false) ?: config('faithfellows.dormancy.warn_days', 30)));
    }

    public function blockAfterDays(): int
    {
        return max(3, (int) (rescue(fn () => PlatformSetting::get('dormancy_block_days'), null, false) ?: config('faithfellows.dormancy.block_days', 14)));
    }

    /** The last time anything happened at the church: set up, a staff sign-in, something published, a new follower or a gift. */
    public function lastActivity(Church $church): Carbon
    {
        $times = [$church->approved_at ?? $church->created_at];

        $times[] = DB::table('church_staff')->join('users', 'users.id', '=', 'church_staff.user_id')
            ->where('church_staff.church_id', $church->id)->where('church_staff.status', 'active')->max('users.last_login_at');
        foreach (self::CONTENT as $table) {
            if (Schema::hasTable($table)) {
                $times[] = DB::table($table)->where('church_id', $church->id)->max('created_at');
            }
        }
        $times[] = DB::table('subscriptions')->where('church_id', $church->id)->max('subscribed_at');
        $times[] = DB::table('donations')->where('church_id', $church->id)->max('created_at');

        return collect($times)->filter()->map(fn ($t) => Carbon::parse($t))->max() ?? now();
    }

    /** @return array{warned: int, blocked: int, cleared: int} */
    public function run(?Carbon $now = null): array
    {
        $now ??= now();
        $warn = $this->warnAfterDays();
        $block = $this->blockAfterDays();
        $result = ['warned' => 0, 'blocked' => 0, 'cleared' => 0];

        Church::where('status', 'active')->where('created_at', '<=', $now->copy()->subDays($warn))->with('owner')->each(function (Church $church) use ($now, $warn, $block, &$result) {
            $last = $this->lastActivity($church);

            if ($church->dormant_warned_at) {
                if ($last->gt($church->dormant_warned_at)) {
                    $church->forceFill(['dormant_warned_at' => null])->save(); // it came back to life
                    $result['cleared']++;
                } elseif ($church->dormant_warned_at->lte($now->copy()->subDays($block))) {
                    $church->forceFill(['status' => 'suspended', 'status_reason' => __('Blocked after :days quiet days', ['days' => $warn + $block]), 'dormant_blocked_at' => $now])->save();
                    Audit::log('church.blocked_dormant', $church, $church->name, [], [], $church->id);
                    Notification::send($church->billingContacts(), new ChurchBlockedForInactivity($church));
                    $result['blocked']++;
                }

                return;
            }

            if ($last->lte($now->copy()->subDays($warn))) {
                $church->forceFill(['dormant_warned_at' => $now])->save();
                Notification::send($church->billingContacts(), new ChurchQuiet($church, (int) $last->diffInDays($now), $block));
                $result['warned']++;
            }
        });

        return $result;
    }
}
