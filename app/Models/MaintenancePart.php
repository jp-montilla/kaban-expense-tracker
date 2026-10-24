<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'vehicle_id', 'name', 'slug', 'interval_km', 'interval_months', 'sort_order'])]
class MaintenancePart extends Model
{
    use BelongsToUser, Trashable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval_km' => 'integer',
            'interval_months' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (MaintenancePart $part): void {
            $part->logs()->with('transaction')->each(function (MaintenanceLog $log): void {
                $log->transaction?->delete();
                $log->delete();
            });
        });
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return HasMany<MaintenanceLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(MaintenanceLog::class);
    }
}
