<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Aflyste aktiviteter uden tilmeldte arkiveres: de vises ikke på aktivitetslisterne, men kan stadig åbnes via et direkte link. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', fn (Blueprint $t) => $t->timestamp('archived_at')->nullable()->after('cancelled_at'));
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $t) => $t->dropColumn('archived_at'));
    }
};
