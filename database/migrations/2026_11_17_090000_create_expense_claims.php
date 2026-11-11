<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Udlæg: et medlem indsender en kvittering og får pengene refunderet af kassereren
        Schema::create('expense_claims', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('description', 1000);
            $t->integer('amount'); // øre
            $t->string('payout_method', 10); // mobilepay | bank
            $t->string('mobilepay_phone', 20)->nullable();
            $t->string('bank_reg', 4)->nullable();
            $t->string('bank_account', 10)->nullable();
            $t->string('file_path');
            $t->string('file_name');
            $t->string('file_mime', 60);
            $t->string('status', 12)->default('submitted'); // submitted | paid | rejected
            // Kassererens egne udlæg skal godkendes af revisoren, før de kan bogføres
            $t->boolean('requires_approval')->default(false);
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('handled_at')->nullable();
            $t->string('reject_reason', 300)->nullable();
            $t->foreignId('entry_id')->nullable()->constrained('entries')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_claims');
    }
};
