<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Web push (VAPID) subscriptions, one per browser/device ───────
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // ── Messages composed by church staff (A6 / notification composer) ─
        Schema::create('church_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id');
            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->string('url', 500)->nullable();
            $table->enum('audience', ['members', 'staff', 'branch', 'group', 'stage'])->default('members');
            $table->json('audience_options')->nullable()->comment('branch_id, small_group_id, stages[]');
            $table->json('channels')->nullable()->comment('Extra channels beyond in-app');
            $table->string('category', 32)->default('announcement');
            $table->boolean('is_urgent')->default(false)->comment('Bypasses quiet hours & digest');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'failed'])->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });

        // ── Delivery log per user × channel (analytics, SMS cost, debugging) ─
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('church_broadcast_id')->nullable()->index();
            $table->string('notification', 80);
            $table->string('category', 32)->nullable();
            $table->string('channel', 20);
            $table->enum('status', ['sent', 'failed', 'deferred'])->default('sent');
            $table->decimal('cost', 10, 2)->default(0)->comment('UGX, SMS/WhatsApp');
            $table->string('provider_ref', 120)->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['church_id', 'created_at']);
            $table->index(['channel', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_digest_at')->nullable()->after('digest_mode');
        });

        // ── Support tickets (church ↔ Kakebe support) ───────────────────
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique()->comment('FF-00001, set after insert');
            $table->unsignedBigInteger('church_id')->nullable()->index('idx_church');
            $table->foreign('church_id')->references('id')->on('churches')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject', 200);
            $table->enum('category', ['technical', 'billing', 'payments', 'account', 'feature', 'other'])->default('technical');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['open', 'pending', 'resolved', 'closed'])->default('open')->index()
                ->comment('pending = waiting for the church');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_staff')->default(false)->comment('Reply from Kakebe support');
            $table->boolean('is_internal')->default(false)->comment('Internal note, hidden from the church');
            $table->timestamps();
        });

        // ── Content reports (members flag abuse; moderators act) ─────────
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->nullable()->index('idx_church');
            $table->morphs('reportable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('reason', ['spam', 'abuse', 'inappropriate', 'false_information', 'other'])->default('inappropriate');
            $table->string('details', 500)->nullable();
            $table->enum('status', ['open', 'actioned', 'dismissed'])->default('open')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->unique(['reportable_type', 'reportable_id', 'user_id'], 'content_report_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_digest_at'));
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('church_broadcasts');
        Schema::dropIfExists('push_subscriptions');
    }
};
