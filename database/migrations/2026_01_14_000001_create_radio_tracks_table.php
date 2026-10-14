<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The songs of the FaithFellows gospel radio: one station for the whole app, set by the super admin.
        Schema::create('radio_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('artist', 160)->nullable();
            $table->string('file_path', 255)->comment('On the private disk; streamed through the app, never linked directly');
            $table->string('mime', 60)->default('audio/mpeg');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('cover_path', 255)->nullable()->comment('Picture taken from the song file, resized');
            $table->unsignedInteger('position')->default(0)->index()->comment('Order in the loop');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_tracks');
    }
};
