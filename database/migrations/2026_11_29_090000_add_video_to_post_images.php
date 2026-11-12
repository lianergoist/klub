<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Video i opslag: samme tabel som billeder (kind = video). Videoen komprimeres i baggrunden (status processing → ready/failed). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_images', function (Blueprint $t) {
            $t->string('kind', 8)->default('image')->after('post_id');
            $t->string('status', 12)->default('ready')->after('kind');
            $t->string('poster_path')->nullable()->after('path');
            $t->unsignedSmallInteger('duration')->nullable(); // sekunder
            $t->unsignedInteger('bytes')->nullable();          // størrelse efter komprimering
            $t->unsignedInteger('original_bytes')->nullable(); // størrelse ved upload
        });
    }

    public function down(): void
    {
        Schema::table('post_images', fn (Blueprint $t) => $t->dropColumn(['kind', 'status', 'poster_path', 'duration', 'bytes', 'original_bytes']));
    }
};
