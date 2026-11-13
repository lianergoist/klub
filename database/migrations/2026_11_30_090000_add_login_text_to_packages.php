<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Linje på login-siden for tilbud til ikke-medlemmer (prøvetræning, kurser, klippekort). Tom = vises ikke. */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $t) {
            $t->string('login_text', 60)->nullable()->after('description');
        });
        // Overfør de faste tekster, der stod på login-siden før
        DB::table('packages')->whereNull('archived_at')->where('kind', 'offer')->where('name', 'like', 'Prøvetræning%')->update(['login_text' => 'Prøv en træning']);
        DB::table('packages')->whereNull('archived_at')->where('kind', 'offer')->where('name', 'like', '%SUP-kursus%')->update(['login_text' => 'Tag et SUP-kursus']);
        DB::table('packages')->whereNull('archived_at')->where('guest_only', true)->where('uses', '>', 0)->update(['login_text' => 'Køb et klippekort til SUP-ture']);
    }

    public function down(): void
    {
        Schema::table('packages', fn (Blueprint $t) => $t->dropColumn('login_text'));
    }
};
