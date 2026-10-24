<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A message can answer an earlier one (swipe a message to reply): in private chats and in groups' conversations. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['direct_messages', 'small_group_posts'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'reply_to_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('reply_to_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['direct_messages', 'small_group_posts'] as $table) {
            if (Schema::hasColumn($table, 'reply_to_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('reply_to_id'));
            }
        }
    }
};
