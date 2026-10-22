<?php

use App\Models\Church;
use App\Services\JoinLinks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every church gets a short code of its own (FF47KM). The link https://faithfellows.com/join/FF47KM opens the app on that church.
        Schema::table('churches', function (Blueprint $table) {
            $table->string('join_code', 24)->nullable()->unique()->after('slug');
        });

        // Each time someone opens a church's link we keep a note of it for the church's figures, and a short-lived fingerprint (a hash of the
        // address and the kind of phone, never the address itself) so the app can recognise a person who installed it right after opening the link.
        Schema::create('join_link_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 12)->default('other'); // android | ios | other
            $table->string('fingerprint', 64)->nullable()->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // Churches that already exist get their code now.
        Church::withTrashed()->whereNull('join_code')->orderBy('id')->each(function (Church $church) {
            $church->forceFill(['join_code' => JoinLinks::generate()])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('join_link_visits');
        Schema::table('churches', function (Blueprint $table) {
            $table->dropUnique(['join_code']);
            $table->dropColumn('join_code');
        });
    }
};
