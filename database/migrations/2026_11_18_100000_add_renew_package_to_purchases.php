<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Bestyrelsen/kassereren kan ændre, hvilket medlemskab et køb fornyes som ved næste opkrævning. */
    public function up(): void
    {
        // Hed tidligere 2026_11_17_100000 og kørte før purchases fandtes på en ny database; allerede kørt under det gamle navn = spring over
        if (Schema::hasColumn('purchases', 'renew_package_id')) return;
        Schema::table('purchases', function (Blueprint $t) {
            $t->foreignId('renew_package_id')->nullable()->after('renewed_from_id')->constrained('packages');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('renew_package_id');
        });
    }
};
