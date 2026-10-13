<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An approximate place (rounded to about 1 km) that a member chooses to share, so others can find believers
        // near them. Never shown as a map point: people only see "about 10 km away".
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('latitude', 6, 3)->nullable()->after('city');
            $table->decimal('longitude', 7, 3)->nullable()->after('latitude');
            $table->timestamp('location_updated_at')->nullable()->after('longitude');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropColumn(['latitude', 'longitude', 'location_updated_at']);
        });
    }
};
