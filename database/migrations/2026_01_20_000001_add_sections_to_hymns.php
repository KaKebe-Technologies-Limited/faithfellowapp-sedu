<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Printed hymn books are divided into parts (entrance, offertory, communion, Christmas, Lent…), and a hymn is known
        // by its part and number. A hymnal lists its parts in order, with what each one means.
        Schema::table('hymnals', function (Blueprint $table) {
            $table->json('sections')->nullable()->after('description')->comment('[{name, meaning}] in book order');
        });

        Schema::table('hymns', function (Blueprint $table) {
            $table->string('section', 120)->nullable()->after('number');
            $table->unsignedSmallInteger('section_order')->default(0)->after('section');
            $table->index(['hymnal_id', 'section_order', 'number']);
        });

        // "From the printed hymn book": a hymn taken from a published hymn book, credited to the person named in it.
        DB::statement("ALTER TABLE hymns MODIFY copyright_status ENUM('public_domain','licensed','original','hymnal') NOT NULL DEFAULT 'public_domain'");
    }

    public function down(): void
    {
        DB::statement("UPDATE hymns SET copyright_status = 'licensed' WHERE copyright_status = 'hymnal'");
        DB::statement("ALTER TABLE hymns MODIFY copyright_status ENUM('public_domain','licensed','original') NOT NULL DEFAULT 'public_domain'");

        Schema::table('hymns', function (Blueprint $table) {
            $table->dropIndex(['hymnal_id', 'section_order', 'number']);
            $table->dropColumn(['section', 'section_order']);
        });
        Schema::table('hymnals', function (Blueprint $table) {
            $table->dropColumn('sections');
        });
    }
};
