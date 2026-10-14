<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete()->comment('Applicant / primary church admin');
            $table->string('name', 160);
            $table->string('slug', 80)->unique();
            $table->string('denomination', 80)->nullable()->index();
            $table->string('tagline', 160)->nullable();
            $table->text('description')->nullable();
            $table->longText('about')->nullable();
            $table->string('pastor_name', 120)->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->json('languages')->nullable();

            // Location
            $table->char('country', 2)->default('UG');
            $table->string('district', 80)->nullable();
            $table->string('city', 80)->nullable()->index();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // Contact
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Branding & website builder
            $table->string('brand_color', 7)->default('#BC2649');
            $table->json('website_blocks')->nullable();
            $table->json('settings')->nullable();
            $table->string('custom_domain')->nullable()->unique();
            $table->boolean('custom_domain_verified')->default(false);

            // Lifecycle: pending → active → suspended → archived
            $table->enum('status', ['pending', 'active', 'suspended', 'archived'])->default('pending')->index();
            $table->text('application_notes')->nullable();
            $table->string('status_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('setup_completed_at')->nullable();
            $table->boolean('requires_member_approval')->default(false);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'city']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id');
            $table->string('name', 120);
            $table->string('slug', 80);
            $table->string('city', 80)->nullable();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('leader_name', 120)->nullable();
            $table->boolean('is_main')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
            $table->unique(['church_id', 'slug']);
        });

        Schema::create('service_times', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id');
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedTinyInteger('day_of_week')->comment('0 = Sunday … 6 = Saturday');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->string('language', 8)->default('en');
            $table->string('location')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
            $table->index(['day_of_week', 'start_time']);
        });

        Schema::create('church_social_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id');
            $table->enum('platform', ['facebook', 'instagram', 'x', 'youtube', 'tiktok', 'whatsapp', 'telegram', 'website', 'other']);
            $table->string('url');
            $table->timestamps();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
            $table->unique(['church_id', 'platform']);
        });

        Schema::create('church_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id');
            $table->string('name', 60);
            $table->string('slug', 60);
            $table->string('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_preset')->default(false);
            $table->timestamps();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
            $table->unique(['church_id', 'slug']);
        });

        Schema::create('church_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_role_id')->nullable()->constrained('church_roles')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete()->comment('Branch-level admin when set');
            $table->boolean('is_admin')->default(false)->comment('Church admin: every permission');
            $table->string('title', 80)->nullable();
            $table->enum('status', ['active', 'invited', 'removed'])->default('active');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
            $table->index('church_id', 'idx_church');
            $table->unique(['church_id', 'user_id']);
        });

        // Media rows belonging to a church (for storage quota & analytics).
        Schema::table('media', function (Blueprint $table) {
            $table->unsignedBigInteger('church_id')->nullable()->after('model_id')->index('idx_church');
        });
    }

    public function down(): void
    {
        Schema::table('media', fn (Blueprint $table) => $table->dropColumn('church_id'));
        Schema::dropIfExists('church_staff');
        Schema::dropIfExists('church_roles');
        Schema::dropIfExists('church_social_links');
        Schema::dropIfExists('service_times');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('churches');
    }
};
