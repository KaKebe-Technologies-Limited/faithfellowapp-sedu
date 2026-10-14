<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The hymn book. Hymnals and hymns are added only by the super admin; every member can read them in the app.
        Schema::create('hymnals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('slug', 190)->unique();
            $table->string('language', 8)->default('en')->index();
            $table->string('denomination', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('source_name', 160)->nullable()->comment('Where the texts were collected, credited on the hymnal page');
            $table->string('source_url', 300)->nullable();
            $table->boolean('is_published')->default(true)->index();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unsignedInteger('hymns_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hymns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hymnal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number')->nullable()->comment('The hymn number in its hymnal');
            $table->string('title', 200);
            $table->string('first_line', 255)->nullable();
            $table->json('verses')->comment('[{number, lines: []}]');
            $table->json('refrain')->nullable()->comment('{lines: []}, sung after every verse');
            $table->text('search_text')->nullable()->comment('All the words, lower case, for searching by any line');
            $table->string('author', 255)->nullable()->comment('Who wrote the words');
            $table->string('translator', 255)->nullable();
            $table->string('composer', 255)->nullable();
            $table->string('arranger', 255)->nullable();
            $table->string('tune', 120)->nullable();
            $table->string('meter', 60)->nullable();
            $table->string('key_signature', 8)->nullable();
            $table->unsignedSmallInteger('year_written')->nullable();
            $table->json('themes')->nullable();
            $table->string('scripture', 200)->nullable();
            $table->enum('copyright_status', ['public_domain', 'licensed', 'original'])->default('public_domain');
            $table->text('credit_note')->nullable()->comment('Credits as the source gives them');
            $table->string('source_url', 400)->nullable();
            $table->string('audio_url', 500)->nullable();
            $table->string('sheet_music_url', 500)->nullable();
            $table->enum('status', ['draft', 'published'])->default('published')->index();
            $table->unsignedInteger('favorites_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['hymnal_id', 'number']);
            $table->index(['hymnal_id', 'title']);
        });

        Schema::create('hymn_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hymn_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'hymn_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hymn_favorites');
        Schema::dropIfExists('hymns');
        Schema::dropIfExists('hymnals');
    }
};
