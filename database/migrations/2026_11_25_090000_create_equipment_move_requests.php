<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anmodning om at flytte en booking af udstyr, som en aktivitet skal bruge.
 * pending → den, der har booket, flytter/annullerer (done), siger nej (declined), eller bookingen annulleres efter fristen (forced).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_move_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users');
            $t->text('message')->nullable();
            $t->string('status', 12)->default('pending');
            $t->text('reply')->nullable();
            $t->dateTime('deadline_at');
            $t->dateTime('answered_at')->nullable();
            $t->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['equipment_booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_move_requests');
    }
};
