<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The pictures the platform admin controls for the app itself: the opening photos, the welcome screen's photos and the default photos behind the
        // Home banner slider. The files are kept by the media library.
        Schema::create('app_photos', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 20)->index();
            $table->string('title', 80)->nullable();
            $table->string('subtitle', 160)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_photos');
    }
};
