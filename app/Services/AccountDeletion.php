<?php

namespace App\Services;

use App\Models\Church;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Deleting a member's account, from the website (Profile → Delete account) and from the app (Me → Delete my account).
 *
 * What goes: the name, email, phone, photo, place, birthday and every personal setting, the phones that get pushes, who they follow,
 * their notes and highlights, saved payment details, activity figures and the like. What stays, without a name: gifts and receipts (the church's
 * books and the law need them) and what they shared with others (messages, prayers, comments), which then show "Deleted user".
 * The public page /delete-account says the same, in plain words; keep the two in step.
 */
class AccountDeletion
{
    /** Tables that only hold this person's own things and are emptied for them. */
    public const PERSONAL_TABLES = [
        'bible_highlights', 'notes', 'device_tokens', 'push_subscriptions', 'notification_preferences', 'notification_deliveries',
        'member_activity', 'hymn_favorites', 'podcast_follows', 'reading_plan_progress', 'quiz_invites', 'user_blocks',
        'payment_methods', 'content_views', 'live_stream_viewers', 'prayer_interactions', 'reactions', 'sessions', 'subscriptions',
    ];

    /** Why this account cannot be deleted by its owner alone, or null when it can. */
    public function blocker(User $user): ?string
    {
        if ($user->isSuperAdmin()) {
            return __('This is a platform account. Please contact support to close it.');
        }
        if (Church::where('owner_id', $user->id)->exists()) {
            return __('You are the owner of a church on FaithFellows. Please contact support so the church can be handed over or closed first.');
        }

        return null;
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            foreach (self::PERSONAL_TABLES as $table) {
                // A table from a newer update may not exist yet on a server that has not applied it.
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('user_id', $user->id)->delete();
                }
            }
            // Gifts that repeat, and promises still open, stop here; the records of what was given stay.
            DB::table('recurring_gifts')->where('user_id', $user->id)->whereIn('status', ['active', 'paused'])->update(['status' => 'cancelled']);
            DB::table('pledges')->where('user_id', $user->id)->whereIn('status', ['active', 'overdue'])->update(['status' => 'cancelled']);

            $user->forceFill([
                'name' => 'Deleted user',
                'email' => 'deleted-'.$user->id.'-'.Str::random(6).'@deleted.faithfellows.com',
                'google_id' => null,
                'password' => Str::random(60),
                'phone' => null,
                'country' => 'UG', // the column cannot be empty; this is its default
                'city' => null,
                'latitude' => null,
                'longitude' => null,
                'location_updated_at' => null,
                'gender' => null,
                'bio' => null,
                'interests' => null,
                'date_of_birth' => null,
                'wedding_anniversary' => null,
                'directory_opt_in' => false,
                'open_to_connect' => false,
                'two_factor_enabled' => false,
                'two_factor_code' => null,
                'last_login_ip' => null,
                'remember_token' => null,
                'status' => 'suspended',
            ])->save();
            $user->clearMediaCollection('avatar');
            $user->tokens()->delete();
            Audit::log('account.deleted', $user);
            $user->delete();
        });
    }
}
