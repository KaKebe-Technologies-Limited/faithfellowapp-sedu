<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Channels that play round the clock, in a loop: the platform's Gospel radio and Podcast radio (set by the super admin), and
        // one channel per church (set by the church). Songs that were added before this update have no channel and belong to the Gospel radio.
        Schema::create('radio_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('slug', 40)->unique()->comment('gospel, podcast, or church-{id}');
            $table->string('kind', 10)->default('music')->comment('music (songs) or talk (podcasts and teaching)');
            $table->string('name', 80);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_blocked')->default(false)->comment('Switched off by the platform (moderation); the church cannot switch it back on');
            $table->timestamps();
        });

        Schema::table('radio_tracks', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('id')->constrained('radio_stations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('radio_tracks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
        });
        Schema::dropIfExists('radio_stations');
    }
};
