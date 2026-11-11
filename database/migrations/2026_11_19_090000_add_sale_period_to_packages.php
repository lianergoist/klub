<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salgsperiode: hvornår på året pakken kan købes (MM-DD, kan gå hen over nytår). Tom = hele gyldighedsperioden.
        Schema::table('packages', function (Blueprint $t) {
            $t->char('sale_from', 5)->nullable()->after('date_to');
            $t->char('sale_to', 5)->nullable()->after('sale_from');
        });
        // De hidtidige helsæsonmedlemskaber, der har en halvsæsonudgave, sælges kun indtil halv sæson begynder
        DB::table('packages')->whereIn('legacy_key', ['m.single', 'm.two'])->update(['sale_from' => '05-01', 'sale_to' => '07-31']);
    }

    public function down(): void
    {
        Schema::table('packages', fn (Blueprint $t) => $t->dropColumn(['sale_from', 'sale_to']));
    }
};
