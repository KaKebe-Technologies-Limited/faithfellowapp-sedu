<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What listeners write under a podcast episode, or under a podcast channel of the radio (the Podcast radio, a church's channel).
        Schema::create('podcast_comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('body', 600);
            $table->enum('status', ['visible', 'hidden', 'flagged'])->default('visible')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('podcast_comments');
    }
};
