<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Collects everything FaithFellows stores about a user for a GDPR-style
 * data export. Tables are included only once they exist (sprints add more).
 */
class UserDataExporter
{
    /**
     * user_id-keyed tables to include, mapped to columns to omit.
     *
     * @var array<string, array<int, string>>
     */
    protected array $tables = [
        'subscriptions' => [],
        'notification_preferences' => [],
        'donations' => ['provider_payload'],
        'pledges' => [],
        'recurring_gifts' => [],
        'event_rsvps' => [],
        'prayer_requests' => [],
        'prayer_interactions' => [],
        'testimonies' => [],
        'comments' => [],
        'reactions' => [],
        'small_group_members' => [],
        'reading_plan_progress' => [],
        'counseling_appointments' => ['notes'],
        'attendance_records' => [],
        'support_tickets' => [],
        'payment_methods' => ['token'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $data = [
            'exported_at' => now()->toIso8601String(),
            'platform' => config('faithfellows.brand.name'),
            'profile' => collect($user->toArray())->except(['roles', 'media'])->all(),
            'roles' => $user->getRoleNames(),
        ];

        foreach ($this->tables as $table => $omit) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                $data[$table] = \DB::table($table)->where('user_id', $user->id)->get()
                    ->map(fn ($row) => collect((array) $row)->except($omit)->all())->all();
            }
        }

        $data['notifications'] = $user->notifications()->latest()->take(500)->get(['type', 'data', 'read_at', 'created_at']);

        return $data;
    }
}
