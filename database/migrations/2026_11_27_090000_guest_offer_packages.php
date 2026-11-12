<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tilbud til ikke-medlemmer (prøvetræning, SUP-kursus …) bliver pakker af typen "offer", som bestyrelsen selv kan oprette og rette.
 * En aktivitet peger på det tilbud, den er åben for (activities.guest_package_id i stedet for den faste kode guest_offer).
 * De to faste tilbud flyttes over med navn, beskrivelse og hele prishistorikken; aktiviteter og gæsteregninger kobles til dem.
 */
return new class extends Migration
{
    private const OLD = [
        'trial' => ['Prøvetræning', 'Prøv en almindelig træning med instruktør. Udstyr og våddragt er med.'],
        'sup_course' => ['SUP-kursus', 'Begynderkursus i stand up paddle – teknik, sikkerhed og en tur på fjorden.'],
    ];

    public function up(): void
    {
        Schema::table('packages', function (Blueprint $t) {
            $t->boolean('creditable')->default(false)->after('guest_only'); // tilbud: modregnes i kontingentet ved indmeldelse inden for 12 måneder
        });
        Schema::table('activities', function (Blueprint $t) {
            $t->foreignId('guest_package_id')->nullable()->after('members_only')->constrained('packages')->nullOnDelete();
        });

        $account = DB::table('accounts')->where('number', '1100')->value('id');
        $pos = 0;
        foreach (self::OLD as $code => [$name, $desc]) {
            $prices = DB::table('prices')->where('code', $code)->orderBy('valid_from')->get();
            $used = DB::table('activities')->where('guest_offer', $code)->exists();
            if ($prices->isEmpty() && ! $used) continue;
            $id = DB::table('packages')->insertGetId([
                'kind' => 'offer', 'name' => $name, 'description' => $desc, 'period_type' => 'once', 'total_min' => 1, 'total_max' => 1,
                'gives_access' => false, 'guest_only' => true, 'creditable' => true, 'self_service' => true, 'account_id' => $account,
                'position' => ++$pos, 'legacy_key' => 'offer.'.$code, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($prices as $p) {
                DB::table('prices')->insert(['code' => 'package.'.$id, 'amount' => $p->amount, 'valid_from' => $p->valid_from,
                    'created_by' => $p->created_by, 'created_at' => $p->created_at, 'updated_at' => now()]);
            }
            DB::table('activities')->where('guest_offer', $code)->update(['guest_package_id' => $id]);
            // Gæsteregninger: husk tilbuddets navn (til teksten ved modregning)
            foreach (DB::table('invoices')->where('kind', 'guest')->get() as $inv) {
                $payload = json_decode($inv->payload ?? '[]', true) ?: [];
                if (($payload['offer'] ?? null) !== $code) continue;
                DB::table('invoices')->where('id', $inv->id)->update(['payload' => json_encode($payload + ['offer_package_id' => $id, 'offer_label' => $name])]);
            }
        }
        Schema::table('activities', fn (Blueprint $t) => $t->dropColumn('guest_offer'));
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $t) => $t->string('guest_offer', 20)->nullable()->after('members_only'));
        foreach (array_keys(self::OLD) as $code) {
            if ($id = DB::table('packages')->where('legacy_key', 'offer.'.$code)->value('id')) {
                DB::table('activities')->where('guest_package_id', $id)->update(['guest_offer' => $code]);
            }
        }
        Schema::table('activities', function (Blueprint $t) { $t->dropConstrainedForeignId('guest_package_id'); });
        $ids = DB::table('packages')->where('kind', 'offer')->pluck('id');
        DB::table('prices')->whereIn('code', $ids->map(fn ($i) => 'package.'.$i))->delete();
        DB::table('packages')->whereIn('id', $ids)->delete();
        Schema::table('packages', fn (Blueprint $t) => $t->dropColumn('creditable'));
    }
};
