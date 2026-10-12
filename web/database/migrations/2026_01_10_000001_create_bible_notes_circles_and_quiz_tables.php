<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 10: in-app Bible, note taker, reading circles (accountability
 * partners), live quizzes, prayer approval flow.
 */
return new class extends Migration
{
    private function tenant(Blueprint $table): void
    {
        $table->unsignedBigInteger('church_id');
        $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
        $table->index('church_id', 'idx_church');
    }

    public function up(): void
    {
        // ── Bible: chapters cached from the provider (fetched once, read forever) ──
        Schema::create('bible_chapters', function (Blueprint $table) {
            $table->id();
            $table->string('translation', 24);
            $table->char('book', 3)->comment('USFM code, e.g. MAT');
            $table->unsignedSmallInteger('chapter');
            $table->json('verses')->comment('[{v, t}]');
            $table->timestamps();
            $table->unique(['translation', 'book', 'chapter']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('bible_translation', 24)->nullable()->after('language');
        });

        // ── Personal notes (sermons, devotionals, Bible chapters, reading-plan days) ──
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('church_id')->nullable()->index();
            $table->string('context', 120)->comment('Stable key, e.g. bible:MAT.3, sermon:12, plan:4:day:7');
            $table->string('reference', 200)->nullable()->comment('Human label shown on the note card');
            $table->string('url', 500)->nullable();
            $table->string('title', 160)->nullable();
            $table->longText('body')->nullable();
            $table->string('color', 20)->default('paper');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'context']);
        });

        // ── Reading circles: up to 4 members reading a plan together ────
        Schema::create('reading_circles', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('reading_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->char('code', 6)->unique()->comment('Pairing code shared with partners');
            $table->time('reading_time')->comment('Agreed daily time (circle timezone)');
            $table->json('reading_days')->comment('0=Sun … 6=Sat');
            $table->string('timezone', 64)->default('Africa/Kampala');
            $table->date('last_reminded_on')->nullable();
            $table->unsignedTinyInteger('max_members')->default(4);
            $table->timestamps();
        });

        Schema::create('reading_circle_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_circle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'member'])->default('member');
            $table->timestamp('joined_at')->useCurrent();
            $table->unique(['reading_circle_id', 'user_id']);
        });

        Schema::create('reading_circle_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_circle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day');
            $table->text('summary')->nullable()->comment('Encrypted; visible to circle members only');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['reading_circle_id', 'user_id', 'day'], 'circle_entry_unique');
        });

        // ── Live quizzes ────────────────────────────────────────────────
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('theme', 20)->default('magenta');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('question', 300);
            $table->json('options')->comment('2–4 answer texts');
            $table->unsignedTinyInteger('correct_option')->comment('Index into options');
            $table->unsignedTinyInteger('time_limit')->default(20)->comment('Seconds');
            $table->unsignedSmallInteger('points')->default(1000);
            $table->timestamps();
        });

        Schema::create('quiz_sessions', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('pin', 6)->index();
            $table->enum('status', ['lobby', 'question', 'reveal', 'finished', 'cancelled'])->default('lobby')->index();
            $table->smallInteger('current_index')->nullable();
            $table->timestamp('question_started_at', 3)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quiz_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nickname', 40);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('streak')->default(0);
            $table->timestamps();
            $table->unique(['quiz_session_id', 'user_id']);
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('option');
            $table->boolean('is_correct');
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedInteger('response_ms');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['quiz_player_id', 'quiz_question_id']);
        });

        // ── Prayer requests: pastor approval before sharing, private replies ──
        DB::statement("ALTER TABLE prayer_requests MODIFY status ENUM('pending', 'open', 'answered', 'hidden', 'declined') NOT NULL DEFAULT 'pending'");
        Schema::table('prayer_requests', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('pastor_response')->nullable()->after('answer_note')->comment('Encrypted');
            $table->foreignId('responded_by')->nullable()->after('pastor_response')->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable()->after('responded_by');
        });
    }

    public function down(): void
    {
        Schema::table('prayer_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('responded_by');
            $table->dropColumn(['approved_at', 'pastor_response', 'responded_at']);
        });
        DB::statement("UPDATE prayer_requests SET status = 'hidden' WHERE status IN ('pending', 'declined')");
        DB::statement("ALTER TABLE prayer_requests MODIFY status ENUM('open', 'answered', 'hidden') NOT NULL DEFAULT 'open'");

        foreach (['quiz_answers', 'quiz_players', 'quiz_sessions', 'quiz_questions', 'quizzes', 'reading_circle_entries', 'reading_circle_members', 'reading_circles', 'notes', 'bible_chapters'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('bible_translation'));
    }
};
