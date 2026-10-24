<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class Ownership
{
    /**
     * @var array<string, bool>
     */
    private static array $trashColumns = [];

    public static function ready(): bool
    {
        return once(static fn () => Schema::hasColumn('categories', 'user_id'));
    }

    public static function hasRole(): bool
    {
        return once(static fn () => Schema::hasColumn('users', 'role'));
    }

    public static function hasLivingPlan(): bool
    {
        return once(static fn () => Schema::hasColumn('users', 'living_plan'));
    }

    public static function hasPartVehicle(): bool
    {
        return once(static fn () => Schema::hasColumn('maintenance_parts', 'vehicle_id'));
    }

    public static function hasTrash(string $table): bool
    {
        return self::$trashColumns[$table] ??= Schema::hasColumn($table, 'deleted_at');
    }
}
