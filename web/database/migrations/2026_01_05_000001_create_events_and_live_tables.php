<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220);
            $table->longText('description')->nullable();
            $table->string('location_name', 160)->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_url', 500)->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('rsvp_enabled')->default(true);
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_cross_branch')->default(false);
            $table->enum('status', ['draft', 'published', 'cancelled'])->default('published');
            $table->enum('visibility', ['public', 'members'])->default('public');
            $table->json('agenda')->nullable()->comment('[{time, title, speaker}]');
            $table->json('speakers')->nullable()->comment('[{name, role, bio}]');
            $table->boolean('qa_enabled')->default(false);
            $table->string('recording_url', 500)->nullable();
            $table->unsignedBigInteger('recording_sermon_id')->nullable();
            $table->timestamp('reminder_24h_sent_at')->nullable();
            $table->timestamp('reminder_1h_sent_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['church_id', 'slug']);
        });

        Schema::create('event_tickets', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('price', 14, 2)->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->unsignedInteger('quantity')->nullable()->comment('NULL = unlimited');
            $table->unsignedInteger('sold_count')->default(0);
            $table->dateTime('sales_end_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->enum('status', ['going', 'maybe', 'pending_payment', 'cancelled'])->default('going');
            $table->string('code', 16)->unique()->comment('QR check-in code');
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedBigInteger('donation_id')->nullable()->index();
            $table->string('seat', 20)->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('event_questions', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('body', 500);
            $table->unsignedInteger('upvotes')->default(0);
            $table->boolean('is_answered')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->enum('status', ['scheduled', 'live', 'ended', 'archived'])->default('scheduled')->index();
            $table->enum('source_type', ['rtmp', 'youtube', 'facebook', 'hls'])->default('rtmp');
            $table->text('stream_key')->nullable()->comment('Encrypted');
            $table->string('stream_key_hash', 64)->nullable()->unique()->comment('Lookup for RTMP callbacks');
            $table->string('external_url', 500)->nullable()->comment('YouTube/Facebook/HLS URL');
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('audio_only_available')->default(true);
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->unsignedInteger('total_viewers')->default(0);
            $table->string('recording_url', 500)->nullable();
            $table->foreignId('sermon_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('live_stream_viewers', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('live_stream_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('viewer_key', 64);
            $table->boolean('audio_only')->default(false);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent()->useCurrentOnUpdate()->index();
            $table->unique(['live_stream_id', 'viewer_key']);
        });

        Schema::create('live_chat_messages', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('live_stream_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('body', 500);
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['live_stream_id', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['live_chat_messages', 'live_stream_viewers', 'live_streams', 'event_questions', 'event_rsvps', 'event_tickets', 'events'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
