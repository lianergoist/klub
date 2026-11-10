<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aktiviteter kan åbnes for ikke-medlemmer som prøvetræning eller SUP-kursus
        Schema::table('activities', function (Blueprint $t) {
            $t->string('guest_offer', 20)->nullable()->after('members_only'); // trial | sup_course
        });
        DB::table('prices')->insert(['code' => 'sup_course', 'amount' => 50000, 'valid_from' => '2026-05-01', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $t) => $t->dropColumn('guest_offer'));
        DB::table('prices')->where('code', 'sup_course')->delete();
    }
};
