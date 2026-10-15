<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a member does in the app, added up for each day (time spent reading the Bible, listening to the radio and podcasts, scriptures
 * and inspiring messages shared…), so the member can see how they are doing on their profile. It is private to the member.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('kind', 24);
            $table->unsignedInteger('seconds')->default(0);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'day', 'kind']);
            $table->index(['user_id', 'kind', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_activity');
    }
};
