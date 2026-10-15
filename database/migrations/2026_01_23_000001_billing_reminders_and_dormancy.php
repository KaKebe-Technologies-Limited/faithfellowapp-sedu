<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing reminders (a week before a payment is due, and the day before) and dormant churches (a warning, then blocking):
 * remember when each message went out so nobody is sent the same one twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->timestamp('issued_notified_at')->nullable()->after('reminded_at');
            $table->timestamp('due_soon_notified_at')->nullable()->after('issued_notified_at');
        });

        Schema::table('churches', function (Blueprint $table) {
            $table->timestamp('dormant_warned_at')->nullable();
            $table->timestamp('dormant_blocked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('churches', fn (Blueprint $table) => $table->dropColumn(['dormant_warned_at', 'dormant_blocked_at']));
        Schema::table('subscription_invoices', fn (Blueprint $table) => $table->dropColumn(['issued_notified_at', 'due_soon_notified_at']));
    }
};
