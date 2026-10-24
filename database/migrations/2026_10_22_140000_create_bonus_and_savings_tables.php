<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();
            $table->date('received_on');
            $table->decimal('amount', 12, 2);
            $table->string('name');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('received_on');
        });

        Schema::create('savings_entries', function (Blueprint $table) {
            $table->id();
            $table->date('occurred_on');
            $table->decimal('amount', 12, 2);
            $table->string('direction');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('occurred_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_entries');
        Schema::dropIfExists('bonuses');
    }
};
