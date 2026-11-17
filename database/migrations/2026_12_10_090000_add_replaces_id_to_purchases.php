<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Skift af medlemskab (fx familie med 3 → 4 eller flere): det nye køb peger på det, det erstatter
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->foreignId('replaces_id')->nullable()->after('renewed_from_id')->constrained('purchases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $t) {
            $t->dropConstrainedForeignId('replaces_id');
        });
    }
};
