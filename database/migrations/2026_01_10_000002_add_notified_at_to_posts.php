<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Church updates (feed posts) now notify followers once, like sermons do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('notified_at'));
    }
};
