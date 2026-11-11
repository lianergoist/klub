<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Købes et medlemskab til næste periode (efter sæsonafslutning), kan medlemmet deltage i aktiviteter
        // fra købsdatoen og frem til periodens start (klubbens praksis). Kan slås fra pr. pakke.
        Schema::table('packages', function (Blueprint $t) {
            $t->boolean('early_access')->default(true)->after('next_from');
        });
        Schema::table('purchases', function (Blueprint $t) {
            $t->date('access_from')->nullable()->after('valid_from'); // tidligere adgang end valid_from (ellers null)
        });
    }

    public function down(): void
    {
        Schema::table('purchases', fn (Blueprint $t) => $t->dropColumn('access_from'));
        Schema::table('packages', fn (Blueprint $t) => $t->dropColumn('early_access'));
    }
};
