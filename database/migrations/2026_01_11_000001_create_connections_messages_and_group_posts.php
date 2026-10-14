<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Members who want to meet other believers say so, and can list what they care about.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('open_to_connect')->default(false)->after('directory_opt_in')->comment('Findable by every adult believer on the app, not only by their churches');
            $table->json('interests')->nullable()->after('bio');
        });

        // A connection request between two members; accepted ones can chat.
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('addressee_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->string('message', 280)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['requester_id', 'addressee_id']);
            $table->index(['addressee_id', 'status']);
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'blocked_id']);
        });

        Schema::create('direct_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['sender_id', 'recipient_id', 'id']);
            $table->index(['recipient_id', 'read_at']);
        });

        // The conversation inside a study group / cell / fellowship.
        Schema::create('small_group_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('church_id')->index('idx_church');
            $table->foreignId('small_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['small_group_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('small_group_posts');
        Schema::dropIfExists('direct_messages');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('connections');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['open_to_connect', 'interests']));
    }
};
