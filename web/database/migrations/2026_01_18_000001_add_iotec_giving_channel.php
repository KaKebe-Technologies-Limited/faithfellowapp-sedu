<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Turn on ioTec Pay giving for every church: one "Mobile money" option (MTN and Airtel) at the top of the list.
        // A church that already has it, or that switched it off on purpose, is left as it is.
        foreach (DB::table('churches')->pluck('id') as $churchId) {
            if (DB::table('giving_channels')->where('church_id', $churchId)->where('type', 'iotec')->exists()) {
                continue;
            }
            DB::table('giving_channels')->where('church_id', $churchId)->increment('sort');
            DB::table('giving_channels')->insert([
                'church_id' => $churchId, 'type' => 'iotec', 'label' => 'Mobile money', 'is_active' => true, 'sort' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('giving_channels')->where('type', 'iotec')->where('label', 'Mobile money')->delete();
    }
};
