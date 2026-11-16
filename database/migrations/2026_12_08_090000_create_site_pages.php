<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hjemmesidens sider som blokke (afløser kopien af Squarespace-siden i resources/site).
 * Indholdet ved konverteringen ligger i database/site/indhold.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug', 60)->unique(); // '' = forsiden
            $t->string('title', 80);           // navnet i menuen
            $t->string('seo_title', 160)->nullable();
            $t->string('description', 300)->nullable();
            $t->boolean('admin_only')->default(false); // kun admin må rette siden (fx Kalender)
            $t->unsignedSmallInteger('position')->default(0);
            $t->json('blocks');
            $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        foreach (require database_path('site/indhold.php') as $i => $p) {
            DB::table('site_pages')->insert([
                'slug' => $p['slug'], 'title' => $p['title'], 'seo_title' => $p['seo_title'], 'description' => $p['description'],
                'admin_only' => $p['admin_only'], 'position' => $i,
                'blocks' => json_encode($p['blocks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
    }
};
