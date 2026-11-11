<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klippekort: en pakke med et antal klip (fx 10), der gælder til aktiviteter i bestemte kategorier (fx SUP),
 * der er "åben for klippekort". Kun for ikke-medlemmer (gæster). Tilmelding bruger et klip; afmelding inden
 * fristen giver det tilbage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $t) {
            $t->unsignedSmallInteger('uses')->nullable()->after('total_max'); // antal klip (null = ikke et klippekort)
            $t->boolean('guest_only')->default(false)->after('requires_membership'); // kun for ikke-medlemmer
        });
        Schema::create('category_package', function (Blueprint $t) {
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->foreignId('package_id')->constrained()->cascadeOnDelete();
            $t->primary(['category_id', 'package_id']);
        });
        Schema::table('purchases', function (Blueprint $t) {
            $t->unsignedSmallInteger('uses_left')->nullable()->after('valid_to');
            $t->string('note', 200)->nullable()->after('uses_left'); // fx "Overført fra papkort"
        });
        Schema::table('activities', function (Blueprint $t) {
            $t->boolean('punchcard')->default(false)->after('guest_offer'); // åben for klippekort
        });
        Schema::table('registrations', function (Blueprint $t) {
            $t->foreignId('purchase_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete(); // klippekortet, der blev brugt
        });
    }

    public function down(): void
    {
        Schema::table('registrations', fn (Blueprint $t) => $t->dropConstrainedForeignId('purchase_id'));
        Schema::table('activities', fn (Blueprint $t) => $t->dropColumn('punchcard'));
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn(['uses_left', 'note']));
        Schema::dropIfExists('category_package');
        Schema::table('packages', fn (Blueprint $t) => $t->dropColumn(['uses', 'guest_only']));
    }
};
