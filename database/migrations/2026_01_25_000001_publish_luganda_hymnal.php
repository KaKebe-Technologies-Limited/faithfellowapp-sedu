<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The owner asked for the Luganda hymn book "Mujje Tutendereze Omukama" to be in the hymn list. Its words are not in git: the book is loaded from its own
        // file, hidden. This shows it once the update is applied, if it has been loaded (and does nothing where it has not). Hide it again any time in
        // Super admin, Hymn book; this update does not run twice.
        if (Schema::hasTable('hymnals')) {
            DB::table('hymnals')->where('slug', 'mujje-tutendereze-omukama')->update(['is_published' => true, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: showing or hiding a book is the super admin's choice.
    }
};
