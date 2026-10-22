<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Member games are played at each person's own pace: every player has their own place in the game, their own clock and their own finish.
        // Games a church hosts on a screen stay as they were ("live": the host moves everyone along together).
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->string('mode', 10)->default('live')->after('status');
        });

        Schema::table('quiz_players', function (Blueprint $table) {
            $table->unsignedSmallInteger('current_index')->default(0)->after('streak');
            $table->timestamp('question_started_at', 3)->nullable()->after('current_index');
            $table->timestamp('revealed_at', 3)->nullable()->after('question_started_at');
            $table->timestamp('started_at', 3)->nullable()->after('revealed_at');
            $table->timestamp('finished_at', 3)->nullable()->after('started_at');
            $table->unsignedInteger('total_ms')->default(0)->after('finished_at');
            $table->index('finished_at');
        });

        // What a person likes to be called. It is the only name other players see on a game board.
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 40)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('nickname'));
        Schema::table('quiz_players', function (Blueprint $table) {
            $table->dropIndex(['finished_at']);
            $table->dropColumn(['current_index', 'question_started_at', 'revealed_at', 'started_at', 'finished_at', 'total_ms']);
        });
        Schema::table('quiz_sessions', fn (Blueprint $table) => $table->dropColumn('mode'));
    }
};
