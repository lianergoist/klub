<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('payer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('membership_types', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code', 20)->unique(); // adult, youth, family, trainer, passive
            $t->unsignedInteger('annual_fee'); // øre
            $t->unsignedTinyInteger('max_age')->nullable();
            $t->timestamps();
        });

        Schema::create('role_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('role', 20); // trainer, board, treasurer, auditor, admin
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('color', 7)->default('#0e7490');
            $t->timestamps();
        });

        Schema::create('category_user', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->boolean('show')->default(true);
            $t->boolean('notify')->default(true);
            $t->primary(['user_id', 'category_id']);
        });

        Schema::create('activities', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('location')->nullable();
            $t->unsignedSmallInteger('max_participants')->nullable();
            $t->unsignedSmallInteger('min_participants')->nullable();
            $t->dateTime('signup_deadline')->nullable();
            $t->unsignedSmallInteger('cancel_hours')->default(24);
            $t->unsignedInteger('price')->default(0); // øre
            $t->boolean('members_only')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });

        Schema::create('activity_category', function (Blueprint $t) {
            $t->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->constrained()->cascadeOnDelete();
            $t->primary(['activity_id', 'category_id']);
        });

        Schema::create('activity_trainer', function (Blueprint $t) {
            $t->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['activity_id', 'user_id']);
        });

        Schema::create('trainer_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 20); // added, removed
            $t->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('registrations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20); // pending_payment, signed_up, cancelled
            $t->boolean('attended')->nullable();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->timestamps();
            $t->unique(['activity_id', 'user_id']);
        });

        Schema::create('registration_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $t->string('action', 20); // signup, cancel, resignup, paid, attended, absent
            $t->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('unavailabilities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('from_date');
            $t->date('to_date');
            $t->string('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->unsignedInteger('price'); // øre, medlemspris
            $t->unsignedInteger('guest_price')->nullable();
            $t->string('billing', 20); // once, season, year
            $t->boolean('needs_resource')->default(false);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('resources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('label'); // fx "Rack A3"
            $t->timestamps();
        });

        Schema::create('product_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->date('valid_from');
            $t->date('valid_to')->nullable();
            $t->timestamps();
        });

        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('number', 10)->unique();
            $t->string('name');
            $t->string('type', 10); // income, expense
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('kind', 20); // membership, activity, product
            $t->string('description');
            $t->unsignedInteger('amount'); // øre
            $t->string('status', 20)->default('open'); // open, paid, cancelled, refunded
            $t->date('due_on');
            $t->string('period', 20)->nullable();
            $t->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('account_id')->constrained();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $t->integer('amount'); // øre, negativ = refusion
            $t->string('method', 20); // mobilepay, card, cash, bank
            $t->string('reference')->nullable();
            $t->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('entries', function (Blueprint $t) {
            $t->id();
            $t->date('entry_date');
            $t->string('text');
            $t->foreignId('account_id')->constrained();
            $t->integer('amount'); // øre, + indtægt, - udgift
            $t->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('reverses_id')->nullable()->constrained('entries')->nullOnDelete();
            $t->string('attachment_path')->nullable();
            $t->string('attachment_name')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('budgets', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year');
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->integer('amount');
            $t->unique(['year', 'account_id']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 40);
            $t->string('subject_type', 40)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('changes')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications','audit_logs','budgets','entries','payments','invoices','accounts','product_orders','resources','products','unavailabilities','registration_events','registrations','trainer_changes','activity_trainer','activity_category','activities','category_user','categories','role_assignments','membership_types','households'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
