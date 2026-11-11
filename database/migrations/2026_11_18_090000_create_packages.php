<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pakker: alt, der kan købes (medlemskaber og tilkøb), beskrives som
 * hvem der er dækket (personer pr. kategori, min–max) + hvor længe det gælder + pris.
 * Bestyrelse og admin opretter pakker under Indstillinger. Intet slettes – pakker og kategorier arkiveres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 40);   // ental, fx Barn
            $t->string('plural', 40); // flertal, fx Børn
            $t->unsignedTinyInteger('min_age')->default(0);
            $t->unsignedTinyInteger('max_age')->nullable(); // null = ingen øvre grænse
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });

        Schema::create('packages', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 12); // membership | product
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->string('period_type', 8); // days | dates | once
            $t->unsignedSmallInteger('period_days')->nullable(); // 1–365
            $t->char('date_from', 5)->nullable(); // MM-DD
            $t->char('date_to', 5)->nullable();   // MM-DD (kan ligge i næste år, fx 05-01 → 04-30)
            $t->unsignedTinyInteger('total_min')->default(1);
            $t->unsignedTinyInteger('total_max')->nullable();
            $t->boolean('gives_access')->default(true);         // medlemskab: adgang til aktiviteter (nej for støttemedlem)
            $t->boolean('requires_membership')->default(false); // tilkøb: kræver gyldigt medlemskab
            $t->boolean('self_service')->default(true);         // medlemmet kan selv vælge/købe den
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete(); // pladser (rack/skab) hænger på et produkt
            $t->foreignId('renews_to_id')->nullable()->constrained('packages')->nullOnDelete(); // fx halv sæson fornyes som hel sæson
            $t->foreignId('account_id')->constrained('accounts');
            $t->unsignedSmallInteger('position')->default(0);
            $t->string('legacy_key', 30)->nullable()->unique(); // pakker overført fra de hidtidige medlemstyper/produkter
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });

        Schema::create('package_slots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('package_id')->constrained()->cascadeOnDelete();
            $t->foreignId('person_category_id')->constrained();
            $t->unsignedTinyInteger('min')->default(0);
            $t->unsignedTinyInteger('max');
            $t->unique(['package_id', 'person_category_id']);
        });

        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('package_id')->constrained();
            $t->foreignId('payer_id')->constrained('users');
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
            $t->date('valid_from');
            $t->date('valid_to')->nullable(); // null = engangskøb / ingen udløb
            $t->foreignId('renewed_from_id')->nullable()->constrained('purchases')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->index(['valid_from', 'valid_to']);
        });

        Schema::create('purchase_user', function (Blueprint $t) {
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['purchase_id', 'user_id']);
        });

        // Overfør de hidtidige medlemstyper, priser, kontingentregninger og tilkøb til pakker og køb
        \App\Support\Packages::backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_user');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('package_slots');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('person_categories');
        \Illuminate\Support\Facades\DB::table('prices')->where('code', 'like', 'package.%')->delete();
    }
};
