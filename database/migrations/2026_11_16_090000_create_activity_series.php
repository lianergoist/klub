<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Faste aktiviteter: samme ugedag og tidspunkt hver uge. Hver gang oprettes som en almindelig aktivitet,
        // så tilmelding, fremmøde, trænerskift og aflysning virker pr. gang.
        Schema::create('activity_series', function (Blueprint $t) {
            $t->id();
            $t->string('title', 150);
            $t->unsignedTinyInteger('weekday'); // 1 = mandag … 7 = søndag (ISO)
            $t->time('start_time');
            $t->time('end_time');
            $t->date('from_date');
            $t->date('to_date');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::table('activities', function (Blueprint $t) {
            $t->foreignId('series_id')->nullable()->after('id')->constrained('activity_series')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activities', fn (Blueprint $t) => $t->dropConstrainedForeignId('series_id'));
        Schema::dropIfExists('activity_series');
    }
};
