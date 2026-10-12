<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Self-running quizzes: the answer is shown for a few seconds, then the next question starts.
        Schema::table('quiz_sessions', function (Blueprint $table) {
            $table->timestamp('revealed_at', 3)->nullable()->after('question_started_at');
        });

        // Every question now runs for the same fixed time (config faithfellows.quizzes.question_seconds).
        DB::table('quiz_questions')->update(['time_limit' => (int) config('faithfellows.quizzes.question_seconds', 10)]);

        Schema::create('bible_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('book', 3);
            $table->unsignedSmallInteger('chapter');
            $table->unsignedSmallInteger('verse');
            $table->string('color', 12);
            $table->timestamps();

            $table->unique(['user_id', 'book', 'chapter', 'verse']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bible_highlights');
        Schema::table('quiz_sessions', fn (Blueprint $table) => $table->dropColumn('revealed_at'));
    }
};
