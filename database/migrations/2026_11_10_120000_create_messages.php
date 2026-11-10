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

        // En samtale har 2 eller flere deltagere. Samtaler med præcis 2 (ikke-gruppe) genbruges mellem de samme to.
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->boolean('is_group')->default(false);
            $t->string('title', 80)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('pair_key', 40)->nullable()->unique(); // "lavId-højId" for 1:1-samtaler
            $t->timestamp('last_message_at')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('conversation_user', function (Blueprint $t) {
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('last_read_message_id')->default(0);
            $t->timestamp('left_at')->nullable();
            $t->timestamps();
            $t->primary(['conversation_id', 'user_id']);
            $t->index('user_id');
        });

        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->boolean('system')->default(false); // fx "Lars forlod samtalen"
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
        foreach (['message_reports', 'user_blocks', 'messages', 'conversation_user', 'conversations'] as $t) Schema::dropIfExists($t);
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('allow_messages'));
    }
};
