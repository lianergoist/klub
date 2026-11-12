<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Udstyr: underkategorier af aktivitetskategorierne (fx Windsurf → Sejl, Master, Boards), det enkelte stykke udstyr med stand og historik,
 * bookinger af bestemte stykker udstyr og ønsker om at låne udstyr, som en aktivitet har booket.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Underkategori af en aktivitetskategori, fx Windsurf → Sejl, Master, Boards
        Schema::create('equipment_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained();
            $t->string('name', 80);
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('equipment', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_type_id')->constrained();
            $t->string('name', 80);              // fx "SUP 3" eller "Sejl 5,8 m² (gul)"
            $t->string('note', 500)->nullable();
            $t->string('status', 10)->default('ok'); // ok, service (skal serviceres – kan bruges), defect (kan ikke bruges)
            $t->string('status_note', 500)->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        Schema::create('equipment_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 20);            // created, edited, status, archived, restored
            $t->string('status', 10)->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::table('activities', function (Blueprint $t) {
            $t->string('status', 10)->default('active')->after('punchcard'); // active, pending (afventer – ikke åben for tilmelding)
            $t->string('equipment_mode', 10)->default('all')->after('status'); // all = alt udstyr i kategorierne, selected = kun valgt udstyr
        });
        Schema::create('activity_equipment', function (Blueprint $t) {
            $t->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->primary(['activity_id', 'equipment_id']);
        });

        Schema::create('equipment_bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('status', 10)->default('confirmed'); // confirmed, requested, rejected, cancelled
            $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->index(['starts_at', 'ends_at']);
        });
        Schema::create('equipment_booking_items', function (Blueprint $t) {
            $t->foreignId('equipment_booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $t->primary(['equipment_booking_id', 'equipment_id']);
        });
        // Ønske om udstyr, som en aktivitet har booket: træneren/tovholderen godkender eller afviser
        Schema::create('equipment_booking_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_booking_id')->constrained()->cascadeOnDelete();
            $t->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $t->string('status', 10)->default('pending'); // pending, accepted, rejected
            $t->string('reply', 500)->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });

        // Medlemmer med udstyrsleje (sæson eller dagsleje) må booke udstyr
        Schema::table('packages', function (Blueprint $t) {
            $t->boolean('equipment_booking')->default(false)->after('guest_only');
        });
        DB::table('packages')->where('name', 'like', 'Udstyrsleje%')->orWhere('name', 'like', 'Dagsleje%')->update(['equipment_booking' => true]);
    }

    public function down(): void
    {
        Schema::table('packages', fn (Blueprint $t) => $t->dropColumn('equipment_booking'));
        Schema::dropIfExists('equipment_booking_approvals');
        Schema::dropIfExists('equipment_booking_items');
        Schema::dropIfExists('equipment_bookings');
        Schema::dropIfExists('activity_equipment');
        Schema::table('activities', fn (Blueprint $t) => $t->dropColumn(['status', 'equipment_mode']));
        Schema::dropIfExists('equipment_events');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('equipment_types');
    }
};
