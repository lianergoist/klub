<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** En video kender først sine mål, når den er behandlet. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_images', function (Blueprint $t) {
            $t->unsignedSmallInteger('width')->nullable()->change();
            $t->unsignedSmallInteger('height')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('post_images', function (Blueprint $t) {
            $t->unsignedSmallInteger('width')->nullable(false)->change();
            $t->unsignedSmallInteger('height')->nullable(false)->change();
        });
    }
};
