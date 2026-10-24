<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'name', 'make', 'model', 'year', 'plate', 'current_odometer', 'notes'])]
class Vehicle extends Model
{
    use BelongsToUser, Trashable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'current_odometer' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Vehicle $vehicle): void {
            $vehicle->logs()->with('transaction')->each(function (MaintenanceLog $log): void {
                $log->transaction?->delete();
                $log->delete();
            });
            $vehicle->parts()->delete();
        });
    }

    /**
     * @return HasMany<MaintenancePart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(MaintenancePart::class);
    }

    /**
     * @return HasMany<MaintenanceLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }
}
