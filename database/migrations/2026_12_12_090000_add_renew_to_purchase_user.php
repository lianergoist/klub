<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Næste sæson pr. person: renew = false, når personen er fjernet fra næste sæson (af betaleren eller bestyrelsen).
     * renew_package_id: personen fornyes på sit eget medlemskab (når husstandens medlemskab deles op, fx 3 → 2 personer).
     */
    public function up(): void
    {
        Schema::table('purchase_user', function (Blueprint $t) {
            $t->boolean('renew')->default(true);
            $t->foreignId('renew_package_id')->nullable()->constrained('packages');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_user', function (Blueprint $t) {
            $t->dropConstrainedForeignId('renew_package_id');
            $t->dropColumn('renew');
        });
    }
};
