<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hvornår påmindelser er sendt (så de kun sendes én gang). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_move_requests', function (Blueprint $t) {
            $t->dateTime('reminded_at')->nullable();         // påmindelse til den, der har booket, før fristen
            $t->dateTime('overdue_notified_at')->nullable(); // besked til træneren, når fristen er overskredet
        });
        Schema::table('activities', function (Blueprint $t) {
            $t->dateTime('pending_reminded_at')->nullable();       // "afventer stadig" til træneren dagen før
            $t->dateTime('pending_board_notified_at')->nullable(); // besked til bestyrelsen kort før start
        });
    }

    public function down(): void
    {
        Schema::table('equipment_move_requests', fn (Blueprint $t) => $t->dropColumn(['reminded_at', 'overdue_notified_at']));
        Schema::table('activities', fn (Blueprint $t) => $t->dropColumn(['pending_reminded_at', 'pending_board_notified_at']));
    }
};
