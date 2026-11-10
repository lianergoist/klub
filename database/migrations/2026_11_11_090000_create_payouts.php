<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Én udbetaling fra betalingsudbyderen til klubbens bankkonto (dækker mange betalinger, fratrukket gebyrer)
        Schema::create('payouts', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 20)->default('mobilepay');
            $t->string('provider_payout_id', 60)->unique(); // udbyderens id – står også i bankteksten
            $t->date('payout_date');
            $t->integer('gross');   // øre, sum af betalinger (refusioner trukket fra)
            $t->integer('fees');    // øre
            $t->integer('net');     // øre, det der lander i banken
            $t->unsignedSmallInteger('payment_count');
            $t->foreignId('fee_entry_id')->nullable()->constrained('entries')->nullOnDelete();
            $t->timestamp('bank_matched_at')->nullable();
            $t->string('bank_text')->nullable();
            $t->timestamps();
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->foreignId('payout_id')->nullable()->after('approved_by')->constrained()->nullOnDelete();
            $t->integer('fee')->nullable()->after('amount'); // øre – kendes først fra afregningsrapporten
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) { $t->dropConstrainedForeignId('payout_id'); $t->dropColumn('fee'); });
        Schema::dropIfExists('payouts');
    }
};
