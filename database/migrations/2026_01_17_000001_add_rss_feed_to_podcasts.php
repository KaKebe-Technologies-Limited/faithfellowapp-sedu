<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A podcast can be connected to the RSS feed of the host it already uses (Podbean, Buzzsprout…): its episodes
        // are then imported and kept up to date from the feed, and the audio stays on the host.
        Schema::table('podcasts', function (Blueprint $table) {
            $table->string('feed_url', 500)->nullable()->after('website_url');
            $table->timestamp('feed_synced_at')->nullable()->after('feed_url');
            $table->string('feed_error', 300)->nullable()->after('feed_synced_at');
            $table->index('feed_url');
        });

        Schema::table('podcast_episodes', function (Blueprint $table) {
            $table->string('guid', 255)->nullable()->after('podcast_id')->comment('The feed id of an imported episode');
            $table->index(['podcast_id', 'guid']);
        });
    }

    public function down(): void
    {
        Schema::table('podcast_episodes', function (Blueprint $table) {
            $table->dropIndex(['podcast_id', 'guid']);
            $table->dropColumn('guid');
        });
        Schema::table('podcasts', function (Blueprint $table) {
            $table->dropIndex(['feed_url']);
            $table->dropColumn(['feed_url', 'feed_synced_at', 'feed_error']);
        });
    }
};
