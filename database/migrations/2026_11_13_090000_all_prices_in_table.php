<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Opbevaring og dagsleje flyttes ind i pristabellen, så bestyrelsen kan redigere alle priser ét sted. */
return new class extends Migration
{
    private array $map = [
        'Opbevaring: plads i boardrack' => ['storage.rack', 60000, 'Plads til ét board og rig i klubhusets rack. Gælder sæsonen (1. maj – 30. april).'],
        'Opbevaring: skab i container' => ['storage.locker', 40000, 'Aflåst skab til våddragt og småudstyr. Gælder sæsonen.'],
        'Dagsleje af board og sejl' => ['rental.day', 10000, 'Til medlemmer uden sæsonleje.'],
    ];

    public function up(): void
    {
        foreach ($this->map as $name => [$code, $amount, $desc]) {
            $price = DB::table('products')->where('name', $name)->value('price') ?? $amount;
            DB::table('prices')->insert(['code' => $code, 'amount' => $price, 'valid_from' => '2026-05-01', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('products')->where('name', $name)->update(['price_code' => $code, 'description' => $desc]);
        }
        // Priser står ikke længere i beskrivelsen (de vises fra pristabellen)
        DB::table('products')->where('name', 'Udstyrsleje i sæsonen')->update(['price_code' => 'rental.single',
            'description' => 'Fri brug af klubbens boards, sejl og våddragter hele sæsonen (1. maj – 30. april). Familiepris fra 3 personer.']);
    }

    public function down(): void
    {
        DB::table('prices')->whereIn('code', ['storage.rack', 'storage.locker', 'rental.day'])->delete();
        DB::table('products')->whereIn('price_code', ['storage.rack', 'storage.locker', 'rental.day'])->update(['price_code' => null]);
    }
};
