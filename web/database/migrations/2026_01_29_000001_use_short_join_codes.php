<?php

use App\Models\Church;
use App\Services\JoinLinks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A church code is FF, two digits and two letters (FF47KM). Codes made in the first version (from the church name) are replaced.
        if (! Schema::hasColumn('churches', 'join_code')) {
            return;
        }

        Church::withTrashed()->orderBy('id')->each(function (Church $church) {
            if (! preg_match('/^FF\d{2}[A-Z]{2}$/', (string) $church->join_code)) {
                $church->forceFill(['join_code' => JoinLinks::generate()])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // The earlier codes are not kept: every church keeps its short code.
    }
};
