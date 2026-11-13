<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Almindelige medlemmers adgang til en rettighed (fx booke udstyr): none | rental | all. NULL = standard.
    public function up(): void
    {
        Schema::table('permission_roles', fn (Blueprint $t) => $t->string('members', 20)->nullable()->after('roles'));
    }

    public function down(): void
    {
        Schema::table('permission_roles', fn (Blueprint $t) => $t->dropColumn('members'));
    }
};
