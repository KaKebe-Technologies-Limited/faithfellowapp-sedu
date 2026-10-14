<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Church subscription plans (Starter UGX 60,000/month…).
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->decimal('price', 14, 2);
            $table->char('currency', 3)->default('UGX');
            $table->enum('interval', ['month', 'year'])->default('month');
            $table->unsignedInteger('storage_mb')->default(5120);
            $table->unsignedInteger('stream_hours')->default(40);
            $table->unsignedSmallInteger('max_admins')->default(10);
            $table->unsignedSmallInteger('max_branches')->default(5);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('churches', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('owner_id')->constrained()->nullOnDelete();
            $table->enum('billing_status', ['trial', 'active', 'past_due', 'suspended', 'cancelled'])->default('trial')->after('status');
            $table->date('trial_ends_at')->nullable()->after('billing_status');
            $table->date('paid_until')->nullable()->after('trial_ends_at');
            $table->boolean('domain_addon')->default(false)->after('custom_domain_verified');
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id');
            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('items');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('total', 14, 2);
            $table->char('currency', 3)->default('UGX');
            $table->date('due_date');
            $table->enum('status', ['unpaid', 'paid', 'overdue', 'void'])->default('unpaid')->index();
            $table->string('reference', 40)->unique();
            $table->string('provider_ref')->nullable()->unique();
            $table->string('method', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
        Schema::table('churches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['billing_status', 'trial_ends_at', 'paid_until', 'domain_addon']);
        });
        Schema::dropIfExists('plans');
    }
};
