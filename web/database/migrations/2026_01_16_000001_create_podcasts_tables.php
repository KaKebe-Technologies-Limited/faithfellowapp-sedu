<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Faith-based podcasts made by members. The audio is hosted elsewhere (the host sites FaithFellows names) and
        // the podcaster only adds the link; the super admin reviews a podcast before it appears in the app.
        Schema::create('podcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('slug', 190)->unique();
            $table->string('host_name', 120)->comment('Who presents it, as listeners see it');
            $table->string('summary', 500);
            $table->text('description')->nullable();
            $table->string('category', 40)->default('teaching')->index();
            $table->string('language', 8)->default('en');
            $table->string('website_url', 300)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'hidden'])->default('pending')->index();
            $table->text('review_note')->nullable()->comment('Why it was not approved, shown to the podcaster');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('episodes_count')->default(0);
            $table->unsignedInteger('followers_count')->default(0);
            $table->timestamp('latest_episode_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('podcast_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('summary')->nullable();
            $table->string('audio_url', 600);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedSmallInteger('season')->nullable();
            $table->unsignedSmallInteger('number')->nullable();
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('plays_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['podcast_id', 'status', 'published_at']);
        });

        Schema::create('podcast_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['podcast_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_follows');
        Schema::dropIfExists('podcast_episodes');
        Schema::dropIfExists('podcasts');
    }
};
