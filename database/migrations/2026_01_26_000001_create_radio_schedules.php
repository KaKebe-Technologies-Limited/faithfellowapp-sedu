<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A song put on air at a set time: "Don Moen Nonstop at 4 pm today". At that time the channel moves to the song and carries on from it in loop order,
        // exactly as if the admin had pressed Play now at that moment. It can be once, every day or every week.
        Schema::create('radio_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('station', 40)->index()->comment('gospel, podcast, or church-{id}');
            $table->foreignId('radio_track_id')->constrained('radio_tracks')->cascadeOnDelete();
            $table->dateTime('starts_at')->comment('The first time it goes on air, in the app time zone');
            $table->string('repeat', 10)->default('none')->comment('none, daily or weekly');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radio_schedules');
    }
};
