<?php

namespace App\Http\Controllers;

use App\Http\Requests\MaintenanceLogRequest;
use App\Http\Requests\MaintenancePartRequest;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\VehicleRequest;
use App\Models\Category;
use App\Models\MaintenanceLog;
use App\Models\MaintenancePart;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\Services\Garage;
use App\Services\Papers;
use App\Support\BikeParts;
use App\Support\Ownership;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function index(Garage $garage, Papers $papers): Response
    {
        $vehicles = Vehicle::query()->orderBy('name')->orderBy('id')->get();

        return Inertia::render('Vehicle/Index', [
            'vehicles' => $vehicles->map(function (Vehicle $row) use ($garage): array {
                $dash = $garage->dashboard($row);

                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'make' => $row->make,
                    'model' => $row->model,
                    'year' => $row->year,
                    'plate' => $row->plate,
                    'current_odometer' => $row->current_odometer,
                    'due_count' => $dash['due_count'],
                    'spent' => $dash['spent'],
                ];
            })->values(),
            'documents' => $papers->all(),
        ]);
    }

    public function show(Vehicle $vehicle, Garage $garage): Response
    {
        return Inertia::render('Vehicle/Show', [
            'vehicles' => Vehicle::query()->orderBy('name')->orderBy('id')->get(['id', 'name'])->map(fn (Vehicle $row): array => [
                'id' => $row->id,
                'name' => $row->name,
            ])->values(),
            ...$garage->dashboard($vehicle),
        ]);
    }

    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $vehicle = Vehicle::query()->create($request->vehicleAttributes());

        if ($request->boolean('seed_bike_parts')) {
            BikeParts::seed($vehicle);
        }

        $this->ensureVehicleCategory();

        return redirect()
            ->route('vehicles.show', $vehicle)
            ->with('success', $vehicle->name.' added.');
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($request->validated());

        return back()->with('success', 'Vehicle saved.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $name = $vehicle->name;
        $vehicle->delete();

        return redirect()->route('vehicles.index')->with('success', $name.' removed.');
    }

    public function storePart(MaintenancePartRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validated();
        $base = BikeParts::slugFor($data['name']);
        $slug = $base;
        $n = 2;

        $partQuery = fn () => Ownership::hasPartVehicle() ? $vehicle->parts() : MaintenancePart::query();

        while ($partQuery()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        $max = (int) $partQuery()->max('sort_order');
        $payload = [
            'name' => $data['name'],
            'slug' => $slug,
            'interval_km' => $data['interval_km'] ?? null,
            'interval_months' => $data['interval_months'] ?? null,
            'sort_order' => $max + 10,
        ];

        if (Ownership::ready()) {
            $payload['user_id'] = $request->user()->id;
        }

        if (Ownership::hasPartVehicle()) {
            $vehicle->parts()->create($payload);
        } else {
            MaintenancePart::query()->create($payload);
        }

        return back()->with('success', 'Part added.');
    }

    public function updatePart(MaintenancePartRequest $request, Vehicle $vehicle, MaintenancePart $part): RedirectResponse
    {
        abort_unless(! Ownership::hasPartVehicle() || $part->vehicle_id === $vehicle->id, 404);

        $part->update($request->validated());

        return back()->with('success', 'Part updated.');
    }

    public function destroyPart(Vehicle $vehicle, MaintenancePart $part): RedirectResponse
    {
        abort_unless(! Ownership::hasPartVehicle() || $part->vehicle_id === $vehicle->id, 404);

        $part->logs()->each(function (MaintenanceLog $log): void {
            $log->transaction?->delete();
            $log->delete();
        });
        $part->delete();

        return back()->with('success', 'Part removed.');
    }

    public function storeLog(MaintenanceLogRequest $request, Vehicle $vehicle, Garage $garage): RedirectResponse
    {
        $data = $request->validated();
        $part = Ownership::hasPartVehicle()
            ? $vehicle->parts()->findOrFail($data['maintenance_part_id'])
            : MaintenancePart::query()->findOrFail($data['maintenance_part_id']);
        $servicedOn = CarbonImmutable::parse($data['serviced_on']);
        $odometer = isset($data['odometer']) && $data['odometer'] !== null ? (int) $data['odometer'] : null;
        $due = $garage->dueDates($part, $servicedOn, $odometer);
        $cost = isset($data['cost']) && $data['cost'] !== '' ? $data['cost'] : null;
        $transactionId = null;

        if ($request->boolean('log_as_expense') && $cost !== null && (float) $cost > 0) {
            $this->ensureVehicleCategory();
            $category = Category::query()->where('slug', 'vehicle')->first()
                ?? Category::query()->where('slug', 'z400')->first();
            $transaction = Transaction::query()->create([
                'occurred_on' => $data['serviced_on'],
                'amount' => $cost,
                'direction' => 'out',
                'type' => 'daily',
                'category_id' => $category?->id,
                'description' => $part->name.' · '.$vehicle->name,
                'notes' => trim(($data['shop'] ?? '').' '.($data['notes'] ?? '')) ?: null,
            ]);
            $transactionId = $transaction->id;
        }

        $vehicle->logs()->create([
            'maintenance_part_id' => $part->id,
            'transaction_id' => $transactionId,
            'serviced_on' => $data['serviced_on'],
            'odometer' => $odometer,
            'cost' => $cost,
            'shop' => $data['shop'] ?? null,
            'next_due_odo' => $data['next_due_odo'] ?? $due['next_due_odo'],
            'next_due_on' => $data['next_due_on'] ?? $due['next_due_on'],
            'notes' => $data['notes'] ?? null,
        ]);

        if ($odometer !== null && $odometer > $vehicle->current_odometer) {
            $vehicle->update(['current_odometer' => $odometer]);
        }

        return back()->with('success', $part->name.' logged.');
    }

    public function destroyLog(Vehicle $vehicle, MaintenanceLog $log): RedirectResponse
    {
        abort_unless($log->vehicle_id === $vehicle->id, 404);

        if ($log->transaction) {
            $log->transaction->delete();
        }

        $log->delete();

        return back()->with('success', 'Service removed.');
    }

    private function ensureVehicleCategory(): void
    {
        $user = request()->user();

        if ($user === null) {
            return;
        }

        $match = ['slug' => 'vehicle'];

        if (Ownership::ready()) {
            $match['user_id'] = $user->id;
        }

        Category::query()->firstOrCreate(
            $match,
            [
                'name' => 'Vehicle',
                'kind' => 'vehicle',
                'color' => '#3a4a38',
                'monthly_plan' => 0,
                'sort_order' => 20,
                'is_active' => true,
            ],
        );
    }
}
