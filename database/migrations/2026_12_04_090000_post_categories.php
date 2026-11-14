<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Opslag kan få kategorier (fx SUP); medlemmer, der følger kategorien, får en push-besked. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_post', function (Blueprint $t) {
            $t->foreignId('post_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->primary(['post_id', 'category_id']);
        });
        // Om opslaget sendte push-beskeder (bruges til grænsen pr. dag)
        Schema::table('posts', fn (Blueprint $t) => $t->boolean('notified')->default(false)->after('body'));
        Schema::table('users', fn (Blueprint $t) => $t->boolean('notify_posts')->default(true)->after('notify_push'));
    }

    public function down(): void
    {
        Schema::dropIfExists('category_post');
        Schema::table('posts', fn (Blueprint $t) => $t->dropColumn('notified'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('notify_posts'));
    }
};
