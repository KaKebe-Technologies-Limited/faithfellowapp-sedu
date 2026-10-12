<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One account across all churches — church membership lives in `subscriptions`.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 32)->nullable()->index();
            $table->char('country', 2)->default('UG');
            $table->string('city', 120)->nullable();
            $table->string('language', 8)->default('en');
            $table->string('timezone', 64)->default('Africa/Kampala');
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('wedding_anniversary')->nullable();
            $table->string('bio', 500)->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active')->index();

            // Notification behaviour (per-church categories live in notification_preferences)
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->boolean('digest_mode')->default(false);

            // Privacy & consent
            $table->boolean('email_consent')->default(false)->comment('Consent to share email with subscribed churches');
            $table->timestamp('terms_accepted_at')->nullable();
            $table->boolean('directory_opt_in')->default(false);
            $table->boolean('low_bandwidth')->default(false);

            // Optional email-OTP two-factor auth (admins)
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_code')->nullable();
            $table->timestamp('two_factor_expires_at')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
