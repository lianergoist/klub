<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Historik for hjemmesidens sider: hver udgivelse gemmes, så man kan se og hente en tidligere udgave. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_page_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_page_id')->constrained('site_pages')->cascadeOnDelete();
            $t->json('blocks');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->index(['site_page_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_page_versions');
    }
};
