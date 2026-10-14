<?php

use App\Support\SampleGames;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Community games: any member can pick one of the ready-made quizzes, start a game, share its PIN and invite the people
 * they have played with. Such quizzes and games belong to no church, so church_id may now be empty. The 25 sample games
 * are added here, so they appear as soon as the database update is applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->unsignedBigInteger('church_id')->nullable()->change();
            $table->string('category', 30)->nullable()->after('theme')->index();
            $table->string('emoji', 8)->nullable()->after('category');
            $table->boolean('is_sample')->default(false)->after('emoji');
        });

        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('church_id')->nullable()->change();
        });

        Schema::create('quiz_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['quiz_session_id', 'user_id']);
        });

        // The tests build their own data (CommunityGamesTest installs the games it needs), so they are not added there.
        if (! app()->runningUnitTests()) {
            SampleGames::install();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_invites');
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn(['category', 'emoji', 'is_sample']));
    }
};
