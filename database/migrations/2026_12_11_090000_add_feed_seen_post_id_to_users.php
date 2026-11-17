<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Seneste opslag, medlemmet har set på opslagstavlen (til tælleren for ulæste opslag) */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('feed_seen_post_id')->nullable()->after('remember_token');
        });
        // Eksisterende medlemmer starter uden ulæste opslag
        DB::table('users')->update(['feed_seen_post_id' => DB::table('posts')->max('id') ?? 0]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('feed_seen_post_id'));
    }
};
