<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A reading circle now holds six members (the owner and five partners) instead of four. */
    public function up(): void
    {
        Schema::table('reading_circles', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_members')->default(6)->change();
        });

        // Circles made while the limit was four get the new limit too.
        DB::table('reading_circles')->where('max_members', 4)->update(['max_members' => 6]);
    }

    public function down(): void
    {
        Schema::table('reading_circles', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_members')->default(4)->change();
        });
    }
};
