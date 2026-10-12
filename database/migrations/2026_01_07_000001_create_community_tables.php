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
        // ── Prayer wall ─────────────────────────────────────────────────
        Schema::create('prayer_requests', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160)->nullable();
            $table->text('body');
            $table->enum('visibility', ['public', 'church', 'pastor'])->default('church')->comment('pastor = only pastoral staff');
            $table->boolean('is_anonymous')->default(false);
            $table->enum('status', ['open', 'answered', 'hidden'])->default('open')->index();
            $table->boolean('chain_enabled')->default(false);
            $table->unsignedInteger('prayed_count')->default(0);
            $table->text('answer_note')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prayer_interactions', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('prayer_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['prayed', 'chain'])->default('prayed');
            $table->string('note', 500)->nullable();
            $table->timestamp('completed_at')->nullable()->comment('Prayer chain: when this link prayed');
            $table->timestamps();
            $table->unique(['prayer_request_id', 'user_id', 'type'], 'prayer_interaction_unique');
        });

        Schema::create('testimonies', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prayer_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->boolean('is_anonymous')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Small groups / cells ────────────────────────────────────────
        Schema::create('small_groups', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('type', 30)->default('cell');
            $table->text('description')->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('meeting_day')->nullable();
            $table->time('meeting_time')->nullable();
            $table->string('location')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_open')->default(true)->comment('Members can request to join');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['church_id', 'slug']);
        });

        Schema::create('small_group_members', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('small_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['member', 'assistant', 'leader'])->default('member');
            $table->enum('status', ['active', 'pending'])->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['small_group_id', 'user_id']);
        });

        Schema::create('small_group_meetings', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('small_group_id')->constrained()->cascadeOnDelete();
            $table->date('meeting_date');
            $table->string('topic', 200)->nullable();
            $table->unsignedSmallInteger('visitors')->default(0);
            $table->decimal('offering_amount', 14, 2)->default(0)->comment('Cell giving to the central fund');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['small_group_id', 'meeting_date']);
        });

        Schema::create('small_group_attendance', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('small_group_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('small_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['present', 'absent', 'excused'])->default('present');
            $table->timestamps();
            $table->unique(['small_group_meeting_id', 'user_id']);
        });

        // ── Volunteers ──────────────────────────────────────────────────
        Schema::create('volunteer_teams', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('color', 7)->default('#BC2649');
            $table->timestamps();
        });

        Schema::create('volunteer_team_members', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('volunteer_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['volunteer_team_id', 'user_id']);
        });

        Schema::create('volunteer_rotas', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('volunteer_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('service_date')->index();
            $table->string('role', 80)->nullable();
            $table->enum('status', ['scheduled', 'confirmed', 'declined'])->default('scheduled');
            $table->string('notes', 255)->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });

        // ── Member care & pastoral tools ────────────────────────────────
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_time_id')->nullable()->constrained()->nullOnDelete();
            $table->date('service_date')->index();
            $table->enum('method', ['qr', 'geofence', 'manual'])->default('qr');
            $table->string('guest_name', 120)->nullable();
            $table->unsignedSmallInteger('headcount')->default(1);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['church_id', 'service_date']);
        });

        Schema::create('pastoral_notes', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Member the note is about');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->comment('Encrypted');
            $table->boolean('is_private')->default(true)->comment('Private = only the author');
            $table->timestamps();
        });

        Schema::create('welfare_records', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name', 120)->nullable();
            $table->string('type', 30)->default('food');
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('given_on');
            $table->text('notes')->nullable()->comment('Encrypted');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('home_visits', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained('users')->nullOnDelete()->comment('Staff visiting');
            $table->dateTime('scheduled_at');
            $table->string('purpose', 200)->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->text('notes')->nullable()->comment('Encrypted');
            $table->timestamps();
        });

        Schema::create('discipleship_records', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('milestone', 40);
            $table->date('completed_on');
            $table->foreignId('mentor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['church_id', 'user_id', 'milestone']);
        });

        Schema::create('counseling_appointments', function (Blueprint $table) {
            $table->id();
            $this->tenant($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counselor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('preferred_at')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(45);
            $table->enum('mode', ['in_person', 'phone', 'video'])->default('in_person');
            $table->text('topic')->comment('Encrypted');
            $table->text('counselor_notes')->nullable()->comment('Encrypted');
            $table->enum('status', ['requested', 'confirmed', 'completed', 'cancelled', 'declined'])->default('requested')->index();
            $table->timestamps();
        });

        // Group-targeted announcements (column created in Sprint 4).
        Schema::table('announcements', function (Blueprint $table) {
            $table->foreign('small_group_id')->references('id')->on('small_groups')->nullOnDelete();
        });
        Schema::table('donations', function (Blueprint $table) {
            $table->foreign('small_group_id')->references('id')->on('small_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('donations', fn (Blueprint $table) => $table->dropForeign(['small_group_id']));
        Schema::table('announcements', fn (Blueprint $table) => $table->dropForeign(['small_group_id']));
        foreach (['counseling_appointments', 'discipleship_records', 'home_visits', 'welfare_records', 'pastoral_notes', 'attendance_records', 'volunteer_rotas', 'volunteer_team_members', 'volunteer_teams', 'small_group_attendance', 'small_group_meetings', 'small_group_members', 'small_groups', 'testimonies', 'prayer_interactions', 'prayer_requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
