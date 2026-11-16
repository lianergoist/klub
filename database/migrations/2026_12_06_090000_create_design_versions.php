<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Klubbens design (farver, logo, skrift, hjørner) til SurfHorsens-temaet: én kladde + udgivne versioner som historik. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_versions', function (Blueprint $t) {
            $t->id();
            $t->enum('status', ['draft', 'published']);
            $t->json('data');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_versions');
    }
};
