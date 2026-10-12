<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the hottest queries found in the Sprint 9 review:
 * unread-notification badge (every page), analytics windows and feed sources.
 */
return new class extends Migration
{
    private const INDEXES = [
        'notifications' => [['notifiable_id', 'notifiable_type', 'read_at'], 'idx_notifications_unread'],
        'content_views' => [['church_id', 'created_at'], 'idx_content_views_church_time'],
        'prayer_requests' => [['church_id', 'status', 'created_at'], 'idx_prayers_wall'],
        'posts' => [['church_id', 'status', 'published_at'], 'idx_posts_feed'],
        'subscriptions' => [['church_id', 'subscribed_at'], 'idx_subscriptions_growth'],
        'live_streams' => [['church_id', 'started_at'], 'idx_live_church_started'],
        'users' => [['last_login_at'], 'idx_users_last_login'],
        'notification_deliveries' => [['church_broadcast_id', 'channel'], 'idx_deliveries_broadcast'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => [$columns, $name]) {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => [, $name]) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
        }
    }
};
