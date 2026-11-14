<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Vises aktiviteten i kalenderen på den offentlige hjemmeside? Standard: nej.
    public function up(): void
    {
        Schema::table('activities', fn (Blueprint $t) => $t->boolean('on_website')->default(false)->after('punchcard'));
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $t) => $t->dropColumn('on_website'));
    }
};
