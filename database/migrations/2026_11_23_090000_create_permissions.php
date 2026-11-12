<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rettigheder: listen over handlinger ligger i App\Support\Permissions. Admin kan ændre, hvilke roller
 * en handling kræver (permission_roles – mangler en række, gælder standarden), og give enkelte medlemmer
 * en rettighed uden at få hele rollen (user_permissions, med start- og slutdato som roller).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_roles', function (Blueprint $t) {
            $t->string('permission', 60)->primary();
            $t->json('roles');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('permission', 60)->index();
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->string('note')->nullable();
            $t->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('permission_roles');
    }
};
