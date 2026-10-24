<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind');
            $table->string('color', 7);
            $table->decimal('monthly_plan', 12, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('creditor')->nullable();
            $table->string('item')->nullable();
            $table->decimal('principal', 12, 2);
            $table->decimal('opening_paid', 12, 2)->default(0);
            $table->decimal('installment_amount', 12, 2)->nullable();
            $table->unsignedSmallInteger('installment_count')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('debt_id')->nullable()->constrained()->nullOnDelete();
            $table->date('occurred_on');
            $table->decimal('amount', 12, 2);
            $table->string('direction');
            $table->string('type');
            $table->string('description');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['occurred_on', 'type']);
        });

        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('installment_no')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('occurred_on');
        });

        Schema::create('monthly_incomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('monthly_incomes');
        Schema::dropIfExists('debt_payments');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('debts');
        Schema::dropIfExists('categories');
    }
};
