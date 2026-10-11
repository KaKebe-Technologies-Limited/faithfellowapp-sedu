<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A believer following a church (one account, many churches).
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['active', 'pending', 'left', 'blocked'])->default('active');
            $table->enum('stage', ['visitor', 'new_member', 'member', 'worker', 'leader'])->default('visitor')
                ->comment('Follow-up workflow: visitor → member → leader');
            $table->boolean('share_email')->default(false);
            $table->string('source', 40)->nullable()->comment('directory, qr, invite, website, campaign…');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
            $table->unique(['user_id', 'church_id']);
            $table->index(['church_id', 'status']);
        });

        // Per-church, per-category channel choices. church_id NULL = global default.
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('church_id')->nullable();
            $table->string('category', 32);
            $table->json('channels');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
            $table->unique(['user_id', 'church_id', 'category'], 'notif_pref_unique');
        });

        // Church feed posts (updates, photos).
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->enum('visibility', ['public', 'members'])->default('public');
            $table->enum('status', ['published', 'hidden'])->default('published');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('subscriptions');
    }
};
