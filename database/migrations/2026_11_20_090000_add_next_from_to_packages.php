<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sæsonafslutning (MM-DD): fra denne dato sælges en pakke med faste datoer til næste periode i stedet for den igangværende
        Schema::table('packages', function (Blueprint $t) {
            $t->char('next_from', 5)->nullable()->after('sale_to');
        });
        // Efter sæsonafslutning (1. november) tilbydes fuldt medlemskab fra næste sæsonstart – ikke halv sæson
        DB::table('packages')->whereIn('legacy_key', ['m.single', 'm.two'])->update(['sale_from' => '11-01', 'sale_to' => '07-31', 'next_from' => '11-01']);
        DB::table('packages')->whereIn('legacy_key', ['m.single_half', 'm.two_half'])->update(['sale_from' => '08-01', 'sale_to' => '10-31']);
        DB::table('packages')->whereIn('legacy_key', ['m.family3', 'm.family4', 'm.passive'])->update(['next_from' => '11-01']);
        DB::table('packages')->whereNull('legacy_key')->where('kind', 'membership')->where('period_type', 'dates')
            ->where('date_from', '05-01')->where('date_to', '04-30')->update(['next_from' => '11-01']);
    }

    public function down(): void
    {
        Schema::table('packages', fn (Blueprint $t) => $t->dropColumn('next_from'));
    }
};
