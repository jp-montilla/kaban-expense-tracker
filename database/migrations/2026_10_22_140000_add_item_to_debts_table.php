<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('debts', 'item')) {
            Schema::table('debts', function (Blueprint $table) {
                $table->string('item')->nullable()->after('creditor');
            });
        }

        foreach (DB::table('debts')->whereNull('item')->get() as $debt) {
            DB::table('debts')->where('id', $debt->id)->update(['item' => $debt->name]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('debts', 'item')) {
            return;
        }

        Schema::table('debts', function (Blueprint $table) {
            $table->dropColumn('item');
        });
    }
};
