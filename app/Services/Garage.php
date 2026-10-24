<?php

namespace App\Services;

use App\Models\MaintenanceLog;
use App\Models\MaintenancePart;
use App\Models\Vehicle;
use App\Support\Money;
use App\Support\Ownership;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class Garage
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(Vehicle $vehicle): array
    {
        $relations = ['logs.part', 'logs.transaction'];

        if (Ownership::hasPartVehicle()) {
            $relations[] = 'parts';
        }

        $vehicle->load($relations);
        $parts = (Ownership::hasPartVehicle()
            ? $vehicle->parts
            : MaintenancePart::query()->get()
        )->sortBy([
            ['sort_order', 'asc'],
            ['name', 'asc'],
        ])->values();
        $logs = $vehicle->logs->sortByDesc(fn (MaintenanceLog $log): string => $log->serviced_on->toDateString().$log->id);
        $today = CarbonImmutable::today();

        $cards = $parts->map(function (MaintenancePart $part) use ($vehicle, $logs, $today): array {
            $last = $logs->first(fn (MaintenanceLog $log): bool => $log->maintenance_part_id === $part->id);
            $nextOdo = $last?->odometer !== null && $part->interval_km
                ? $last->odometer + $part->interval_km
                : $last?->next_due_odo;
            $nextOn = $last && $part->interval_months
                ? $last->serviced_on->addMonths($part->interval_months)
                : $last?->next_due_on;
            $kmLeft = $nextOdo !== null ? $nextOdo - $vehicle->current_odometer : null;
            $daysLeft = $nextOn === null ? null : (int) $today->diffInDays($nextOn->toImmutable()->startOfDay(), false);

            return [
                'id' => $part->id,
                'name' => $part->name,
                'slug' => $part->slug,
                'interval_km' => $part->interval_km,
                'interval_months' => $part->interval_months,
                'last_on' => $last?->serviced_on->toDateString(),
                'last_odo' => $last?->odometer,
                'last_cost' => $last?->cost === null ? null : (float) $last->cost,
                'next_due_odo' => $nextOdo,
                'next_due_on' => $nextOn?->toDateString(),
                'km_left' => $kmLeft,
                'days_left' => $daysLeft,
                'status' => $this->status($last, $vehicle->current_odometer, $today, $nextOdo, $nextOn),
            ];
        })->values();

        return [
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'plate' => $vehicle->plate,
                'current_odometer' => $vehicle->current_odometer,
                'notes' => $vehicle->notes,
            ],
            'parts' => $parts->map(fn (MaintenancePart $part): array => [
                'id' => $part->id,
                'name' => $part->name,
                'slug' => $part->slug,
                'interval_km' => $part->interval_km,
                'interval_months' => $part->interval_months,
            ])->values(),
            'cards' => $cards,
            'logs' => $logs->map(fn (MaintenanceLog $log): array => $log->toListItem())->values(),
            'spent' => Money::pesos((int) $logs->sum(fn (MaintenanceLog $log): int => Money::cents($log->cost))),
            'due_count' => $cards->filter(fn (array $card): bool => in_array($card['status'], ['overdue', 'soon'], true))->count(),
        ];
    }

    /**
     * @param  Collection<int, MaintenancePart>  $parts
     */
    public function dueDates(MaintenancePart $part, CarbonImmutable $servicedOn, ?int $odometer): array
    {
        return [
            'next_due_odo' => $odometer !== null && $part->interval_km ? $odometer + $part->interval_km : null,
            'next_due_on' => $part->interval_months ? $servicedOn->addMonths($part->interval_months)->toDateString() : null,
        ];
    }

    private function status(?MaintenanceLog $last, int $currentOdo, CarbonImmutable $today, ?int $nextOdo, mixed $nextOn): string
    {
        if ($last === null) {
            return 'never';
        }

        $dueDate = $nextOn instanceof CarbonInterface ? CarbonImmutable::parse($nextOn->toDateString()) : null;
        $odoOverdue = $nextOdo !== null && $currentOdo >= $nextOdo;
        $dateOverdue = $dueDate !== null && $today->greaterThan($dueDate->startOfDay());

        if ($odoOverdue || $dateOverdue) {
            return 'overdue';
        }

        $odoSoon = $nextOdo !== null && ($nextOdo - $currentOdo) <= 500;
        $dateSoon = $dueDate !== null && $today->diffInDays($dueDate->startOfDay()) <= 30;

        if ($odoSoon || $dateSoon) {
            return 'soon';
        }

        return 'ok';
    }
}
