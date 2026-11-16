<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Egne farvetemaer (Indstillinger → Design) afløser kladde/versioner fra dagen før.
 * De indbyggede temaer (SurfHorsens m.fl.) ligger i app.css og kan ikke rettes – man redigerer en kopi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('design_versions');
        Schema::create('design_themes', function (Blueprint $t) {
            $t->id();
            $t->string('name', 40)->unique();
            $t->enum('status', ['available', 'development'])->default('development');
            $t->json('data');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_themes');
    }
};
