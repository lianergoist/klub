<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ventende handling, der først udføres når regningen er betalt (fx tilføjelse af familiemedlemmer)
        Schema::table('invoices', function (Blueprint $t) {
            $t->json('payload')->nullable()->after('account_id');
        });

        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body')->nullable();
            $t->timestamp('hidden_at')->nullable();
            $t->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('hidden_reason')->nullable();
            $t->timestamps();
            $t->index('created_at');
        });

        Schema::create('post_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->unsignedSmallInteger('width');
            $t->unsignedSmallInteger('height');
            $t->unsignedTinyInteger('position')->default(0);
        });

        Schema::create('post_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamp('hidden_at')->nullable();
            $t->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('post_likes', function (Blueprint $t) {
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->primary(['post_id', 'user_id']);
        });

        Schema::create('post_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('reason', 300);
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->unique(['post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['post_reports', 'post_likes', 'post_comments', 'post_images', 'posts'] as $t) Schema::dropIfExists($t);
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('payload'));
    }
};
