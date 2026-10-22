<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reactions were a fixed list of four. They are text now, so like and smile (and any other later) can be stored; the app decides which it offers.
        Schema::table('reactions', function (Blueprint $table) {
            $table->string('type', 20)->default('amen')->change();
        });
    }

    public function down(): void
    {
        // Text stays text: the earlier four are all still valid values.
    }
};
