<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which platform-wide features a church's members see in the app: podcasts, the hymn book and the gospel radio.
 * All on by default (every church sees them); only the super admin switches them off, per church.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->boolean('show_podcasts')->default(true)->after('is_featured');
            $table->boolean('show_hymns')->default(true)->after('show_podcasts');
            $table->boolean('show_radio')->default(true)->after('show_hymns');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn(['show_podcasts', 'show_hymns', 'show_radio']);
        });
    }
};
