<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function tenant(Blueprint $table): void
    {
        $table->unsignedBigInteger('church_id');
        $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
        $table->index('church_id', 'idx_church');
    }

    public function up(): void
    {
        Schema::create('giving_channels', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->enum('type', ['iotec', 'mtn_momo', 'airtel_money', 'stripe', 'bank', 'ussd', 'cash']);
            $table->string('label', 120)->nullable();
            $table->text('details')->nullable()->comment('Encrypted JSON: account name/number, bank, merchant code…');
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['church_id', 'type']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique()->comment('Globally unique: /campaigns/{slug}');
            $table->string('type', 30)->default('custom');
            $table->string('short_description', 300)->nullable();
            $table->longText('story')->nullable();
            $table->decimal('goal_amount', 16, 2);
            $table->char('currency', 3)->default('UGX');
            $table->decimal('raised_amount', 16, 2)->default(0);
            $table->decimal('pledged_amount', 16, 2)->default(0);
            $table->unsignedInteger('givers_count')->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('active')->index();
            $table->enum('visibility', ['public', 'members'])->default('public');
            $table->boolean('show_leaderboard')->default(true);
            $table->boolean('allow_anonymous')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('goal_reached_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campaign_milestones', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('percent');
            $table->string('title', 160)->nullable();
            $table->timestamp('reached_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'percent']);
        });

        Schema::create('campaign_updates', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->longText('body')->nullable();
            $table->boolean('is_testimony')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_teams', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->foreignId('captain_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('goal_amount', 16, 2)->nullable();
            $table->decimal('raised_amount', 16, 2)->default(0);
            $table->string('color', 7)->default('#BC2649');
            $table->timestamps();
        });

        Schema::create('pledges', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30)->default('project');
            $table->decimal('amount', 16, 2);
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->enum('frequency', ['once', 'weekly', 'monthly', 'quarterly'])->default('monthly');
            $table->unsignedSmallInteger('installments')->default(1);
            $table->date('start_date');
            $table->date('due_date')->nullable();
            $table->date('next_reminder_on')->nullable();
            $table->enum('status', ['active', 'fulfilled', 'cancelled', 'overdue'])->default('active')->index();
            $table->string('note', 500)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();
        });

        Schema::create('recurring_gifts', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30)->default('tithe');
            $table->decimal('amount', 16, 2);
            $table->char('currency', 3)->default('UGX');
            $table->enum('frequency', ['weekly', 'monthly', 'quarterly']);
            $table->string('method', 20);
            $table->string('phone', 32)->nullable();
            $table->date('next_run_on')->index();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedTinyInteger('failures')->default(0);
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active')->index();
            $table->timestamps();
        });

        // User-level saved payment methods (never raw card data — PCI handled by gateways).
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['mtn_momo', 'airtel_money', 'iotec', 'card']);
            $table->string('label', 80)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->text('token')->nullable()->comment('Encrypted gateway token / payment method id');
            $table->string('last4', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->decimal('amount', 16, 2);
            $table->char('currency', 3)->default('UGX');
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending')->index();
            $table->string('method', 40)->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pledge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recurring_gift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_rsvp_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('small_group_id')->nullable()->index();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30)->index();
            $table->decimal('amount', 16, 2);
            $table->decimal('fee', 16, 2)->default(0);
            $table->char('currency', 3)->default('UGX');
            $table->string('method', 20)->index();
            $table->string('reference', 40)->unique()->comment('Our reference (sent to the gateway)');
            $table->string('provider_ref')->nullable()->unique()->comment('Gateway transaction id — idempotency key');
            $table->string('phone', 32)->nullable();
            $table->enum('status', ['pending', 'processing', 'succeeded', 'failed', 'cancelled', 'refunded', 'disputed'])->default('pending')->index();
            $table->string('failure_reason')->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('show_on_leaderboard')->default(false);
            $table->string('giver_name', 120)->nullable()->comment('Cash / offline givers');
            $table->string('note', 500)->nullable();
            $table->json('provider_payload')->nullable();
            $table->string('receipt_number', 30)->nullable()->unique();
            $table->timestamp('receipt_sent_at')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['church_id', 'status', 'paid_at']);
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->string('batch', 40)->index();
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->string('reference')->nullable()->index();
            $table->decimal('amount', 16, 2);
            $table->foreignId('donation_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['unmatched', 'matched', 'ignored'])->default('unmatched')->index();
            $table->timestamps();
        });

        // Comments & reactions on campaigns, posts, sermons, testimonies…
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->morphs('commentable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->enum('status', ['visible', 'hidden', 'flagged'])->default('visible')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->morphs('reactable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['amen', 'love', 'pray', 'celebrate'])->default('amen');
            $table->timestamps();
            $table->unique(['reactable_type', 'reactable_id', 'user_id'], 'reaction_unique');
        });
    }

    public function down(): void
    {
        foreach (['reactions', 'comments', 'bank_statement_lines', 'donations', 'payouts', 'payment_methods', 'recurring_gifts', 'pledges', 'campaign_teams', 'campaign_updates', 'campaign_milestones', 'campaigns', 'giving_channels'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
