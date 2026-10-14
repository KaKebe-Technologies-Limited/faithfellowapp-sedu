<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel database notifications (in-app notification center).
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->unsignedBigInteger('church_id')->nullable()->index('idx_church');
            $table->string('category', 32)->nullable()->index();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Audit trail for admin actions. church_id is nullable because
        // platform-level (super admin) actions are not tied to a church.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->nullable()->index('idx_church');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('impersonator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->nullableMorphs('auditable');
            $table->string('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['new', 'read', 'replied'])->default('new')->index();
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 60)->default('Resources')->index();
            $table->string('excerpt', 400)->nullable();
            $table->longText('body');
            $table->unsignedSmallInteger('reading_minutes')->default(3);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        // Platform key/value settings (gateway toggles, global announcement…).
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
    }
};
