<?php

use App\Models\Church;
use App\Models\GivingReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giving reasons: each church writes its own list of what members can give for (Tithe, Offering, Building fund, a
 * thanksgiving…). A gift keeps the reason's name as it was when it was given, so the church's records stay right even if a
 * reason is renamed or removed later. Every church starts with a few common reasons it can change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giving_reasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('category', 30)->default('offering')->comment('The platform category it counts as, for reports and older apps');
            $table->string('description', 160)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['church_id', 'name']);
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->foreignId('giving_reason_id')->nullable()->after('campaign_team_id')->constrained('giving_reasons')->nullOnDelete();
            $table->string('reason', 80)->nullable()->after('category')->index();
        });

        Schema::table('recurring_gifts', function (Blueprint $table) {
            $table->foreignId('giving_reason_id')->nullable()->after('campaign_id')->constrained('giving_reasons')->nullOnDelete();
        });

        Church::query()->each(fn (Church $church) => GivingReason::seedDefaults($church));
    }

    public function down(): void
    {
        Schema::table('recurring_gifts', fn (Blueprint $table) => $table->dropConstrainedForeignId('giving_reason_id'));
        Schema::table('donations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('giving_reason_id');
            $table->dropColumn('reason');
        });
        Schema::dropIfExists('giving_reasons');
    }
};
