<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the standard tenant columns: church_id FK (cascade) + idx_church.
     */
    private function tenant(Blueprint $table): void
    {
        $table->unsignedBigInteger('church_id');
        $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
        $table->index('church_id', 'idx_church');
    }

    public function up(): void
    {
        Schema::create('sermon_series', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->string('title', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['church_id', 'slug']);
        });

        Schema::create('sermons', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sermon_series_id')->nullable()->constrained('sermon_series')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Uploaded by');
            $table->string('title', 200);
            $table->string('slug', 220);
            $table->string('preacher', 120)->nullable()->index();
            $table->string('scripture', 160)->nullable();
            $table->longText('description')->nullable();
            $table->string('audio_url', 500)->nullable()->comment('External audio (when not uploaded)');
            $table->string('video_url', 500)->nullable()->comment('YouTube / Facebook / MP4 URL');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('language', 8)->default('en')->index();
            $table->enum('status', ['draft', 'scheduled', 'published'])->default('draft');
            $table->enum('visibility', ['public', 'members'])->default('public');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->date('preached_on')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('listens_count')->default(0);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['church_id', 'slug']);
            $table->index(['status', 'published_at']);
        });

        // Phase 2 (Sermon AI) — transcripts are stored now; manual entry supported today.
        Schema::create('sermon_transcripts', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('sermon_id')->constrained()->cascadeOnDelete();
            $table->string('language', 8)->default('en');
            $table->longText('body')->nullable();
            $table->text('summary')->nullable();
            $table->json('key_verses')->nullable();
            $table->json('discussion_questions')->nullable();
            $table->enum('source', ['manual', 'ai'])->default('manual');
            $table->enum('status', ['queued', 'processing', 'completed', 'failed', 'unavailable'])->default('completed');
            $table->string('provider', 40)->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['sermon_id', 'language']);
        });

        Schema::create('playlists', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('playlist_sermon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sermon_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->unique(['playlist_id', 'sermon_id']);
        });

        Schema::create('devotionals', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220);
            $table->string('scripture', 160)->nullable();
            $table->text('scripture_text')->nullable();
            $table->longText('body');
            $table->string('prayer', 1000)->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->date('publish_date')->index();
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->enum('visibility', ['public', 'members'])->default('public');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['church_id', 'slug']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('small_group_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->longText('body');
            $table->enum('audience', ['public', 'members', 'staff', 'branch', 'group'])->default('members');
            $table->enum('priority', ['normal', 'urgent'])->default('normal');
            $table->enum('status', ['draft', 'scheduled', 'published'])->default('published');
            $table->json('channels')->nullable()->comment('Extra delivery: mail, sms, whatsapp');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        Schema::create('reading_plans', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->string('title', 160);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('days_count');
            $table->json('entries')->comment('[{day, title, passages, reflection}]');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['church_id', 'slug']);
        });

        Schema::create('reading_plan_progress', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('reading_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('started_on');
            $table->json('completed_days');
            $table->unsignedSmallInteger('current_streak')->default(0);
            $table->unsignedSmallInteger('longest_streak')->default(0);
            $table->date('last_read_on')->nullable();
            $table->time('reminder_time')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['reading_plan_id', 'user_id']);
        });

        // Engagement events for analytics (views, listens, watch time, downloads).
        Schema::create('content_views', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->morphs('viewable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('action', ['view', 'listen', 'watch', 'download'])->default('view');
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['content_views', 'reading_plan_progress', 'reading_plans', 'announcements', 'devotionals', 'playlist_sermon', 'playlists', 'sermon_transcripts', 'sermons', 'sermon_series'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
