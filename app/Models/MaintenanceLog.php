<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['vehicle_id', 'maintenance_part_id', 'transaction_id', 'serviced_on', 'odometer', 'cost', 'shop', 'next_due_odo', 'next_due_on', 'notes'])]
class MaintenanceLog extends Model
{
    use Trashable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'serviced_on' => 'date',
            'odometer' => 'integer',
            'cost' => 'decimal:2',
            'next_due_odo' => 'integer',
            'next_due_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<MaintenancePart, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(MaintenancePart::class, 'maintenance_part_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(): array
    {
        return [
            'id' => $this->id,
            'part_id' => $this->maintenance_part_id,
            'part' => $this->part?->name,
            'serviced_on' => $this->serviced_on->toDateString(),
            'odometer' => $this->odometer,
            'cost' => $this->cost === null ? null : (float) $this->cost,
            'shop' => $this->shop,
            'next_due_odo' => $this->next_due_odo,
            'next_due_on' => $this->next_due_on?->toDateString(),
            'notes' => $this->notes,
            'has_expense' => $this->transaction_id !== null,
        ];
    }
}
