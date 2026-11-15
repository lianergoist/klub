<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brugsmønstre: sideskift, fejl og (senere) forløb. Pseudonymt – ingen bruger-id, ingen indtastede værdier.
 * Slettes automatisk efter 90 dage (se routes/console.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $t) {
            $t->id();
            $t->string('session', 32)->nullable();     // tilfældigt id pr. browserfane/app-start
            $t->string('who', 16)->nullable();         // pseudonym (HMAC af bruger-id), skifter hver måned
            $t->string('roles', 100)->nullable();      // fx "member,trainer" eller "guest"
            $t->string('platform', 10)->nullable();    // web / app
            $t->string('type', 20);                    // view, error, js_error, track
            $t->string('path', 191);                   // ruteskabelon, fx /aktiviteter/:id eller api/activities/{activity}
            $t->string('target', 191)->nullable();     // fx HTTP-metode+status eller forløbsnavn
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
            $t->index(['session', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
