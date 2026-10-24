<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $ownedTables = [
        'categories',
        'debts',
        'transactions',
        'monthly_incomes',
        'salaries',
        'vehicles',
        'maintenance_parts',
        'bonuses',
        'savings_entries',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('member')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->decimal('living_plan', 12, 2)->default(17000)->after('is_active');
        });

        foreach ($this->ownedTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->foreignId('vehicle_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        $ownerId = DB::table('users')->orderBy('id')->value('id');

        if ($ownerId) {
            $living = DB::table('settings')->where('key', 'living_plan')->value('value');

            DB::table('users')->where('id', $ownerId)->update([
                'role' => 'admin',
                'is_active' => true,
                'living_plan' => $living !== null ? $living : 17000,
            ]);

            foreach ($this->ownedTables as $name) {
                DB::table($name)->whereNull('user_id')->update(['user_id' => $ownerId]);
            }

            $vehicleId = DB::table('vehicles')->orderBy('id')->value('id');

            if ($vehicleId) {
                DB::table('maintenance_parts')->whereNull('vehicle_id')->update(['vehicle_id' => $vehicleId]);
            }
        }

        $this->dropUniqueIndex('categories', 'categories_slug_unique');
        $this->dropUniqueIndex('salaries', 'salaries_effective_on_unique');
        $this->dropUniqueIndex('monthly_incomes', 'monthly_incomes_year_month_unique');
        $this->dropUniqueIndex('maintenance_parts', 'maintenance_parts_slug_unique');

        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['user_id', 'slug']);
        });

        Schema::table('salaries', function (Blueprint $table) {
            $table->unique(['user_id', 'effective_on']);
        });

        Schema::table('monthly_incomes', function (Blueprint $table) {
            $table->unique(['user_id', 'year', 'month']);
        });

        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->unique(['vehicle_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'slug']);
        });
        Schema::table('salaries', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'effective_on']);
        });
        Schema::table('monthly_incomes', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'year', 'month']);
        });
        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->dropUnique(['vehicle_id', 'slug']);
        });

        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
        });

        foreach ($this->ownedTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->unique('slug');
        });
        Schema::table('salaries', function (Blueprint $table) {
            $table->unique('effective_on');
        });
        Schema::table('monthly_incomes', function (Blueprint $table) {
            $table->unique(['year', 'month']);
        });
        Schema::table('maintenance_parts', function (Blueprint $table) {
            $table->unique('slug');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'living_plan']);
        });
    }

    private function dropUniqueIndex(string $table, string $index): void
    {
        $indexes = collect(Schema::getIndexes($table));

        if ($indexes->contains(fn (array $row): bool => ($row['name'] ?? '') === $index)) {
            Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                $blueprint->dropUnique($index);
            });
        }
    }
};
