<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40);
            $t->integer('amount'); // øre
            $t->date('valid_from');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['code', 'valid_from']);
        });

        // Priser fra bestyrelsen (gælder fra sæsonstart 1. maj 2026)
        $from = '2026-05-01';
        foreach ([
            'membership.passive' => 40000, 'membership.single' => 100000, 'membership.single_half' => 40000,
            'membership.family3' => 250000, 'membership.family4' => 300000,
            'rental.single' => 150000, 'rental.family' => 400000, 'rental.extra' => 100000,
            'trial' => 40000, 'key_deposit' => 10000,
        ] as $code => $amount) {
            DB::table('prices')->insert(['code' => $code, 'amount' => $amount, 'valid_from' => $from, 'created_at' => now(), 'updated_at' => now()]);
        }

        Schema::table('products', function (Blueprint $t) {
            $t->string('price_code', 40)->nullable()->after('price');   // pris hentes fra pristabellen
            $t->string('pricing', 20)->default('fixed')->after('price_code'); // fixed | household (udstyrsleje pr. husstand)
        });

        // Medlemstyper: Enkelt medlem, Familie, Støttemedlem. Barn/ung og Træner udgår (betaler som enkelt medlem).
        if (DB::table('membership_types')->exists()) {
            $single = DB::table('membership_types')->where('code', 'adult')->value('id');
            if ($single) {
                DB::table('membership_types')->where('id', $single)->update(['code' => 'single', 'name' => 'Enkelt medlem', 'max_age' => null]);
                $old = DB::table('membership_types')->whereIn('code', ['youth', 'trainer'])->pluck('id');
                DB::table('users')->whereIn('membership_type_id', $old)->update(['membership_type_id' => $single]);
                DB::table('membership_types')->whereIn('id', $old)->delete();
            }
            DB::table('membership_types')->where('code', 'passive')->update(['name' => 'Støttemedlem']);
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['price_code', 'pricing']));
        Schema::dropIfExists('prices');
    }
};
