<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('allow_messages')->default(true)->after('notify_push');
        });

        // Samtale mellem præcis to medlemmer (user_a_id < user_b_id, så hvert par kun findes én gang)
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_a_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('user_b_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('last_message_at')->nullable()->index();
            $t->timestamps();
            $t->unique(['user_a_id', 'user_b_id']);
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->text('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['conversation_id', 'id']);
        });

        Schema::create('user_blocks', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->primary(['user_id', 'blocked_id']);
        });

        Schema::create('message_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('reason', 300);
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['message_reports', 'user_blocks', 'messages', 'conversations'] as $t) Schema::dropIfExists($t);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('allow_messages'));
    }
};
