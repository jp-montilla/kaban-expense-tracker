<?php

namespace Tests\Feature;

use App\Models\MaintenanceLog;
use App\Models\MaintenancePart;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\LedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_z400_page_shows_last_oil_change(): void
    {
        $this->seed(LedgerSeeder::class);

        $this->actingAs(User::owner())
            ->get('/vehicles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vehicle/Index')
                ->where('vehicles', fn ($rows) => $rows->contains('name', 'Z400')));

        $vehicle = Vehicle::query()->where('name', 'Z400')->firstOrFail();

        $this->actingAs(User::owner())
            ->get("/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Vehicle/Show')
                ->where('vehicle.name', 'Z400')
                ->where('cards', function ($cards) {
                    $oil = $cards->firstWhere('name', 'Engine oil');
                    $filter = $cards->firstWhere('name', 'Oil filter');
                    $air = $cards->firstWhere('name', 'Air filter');

                    return $oil !== null
                        && $oil['last_on'] === '2026-04-14'
                        && $oil['interval_km'] === 6000
                        && $oil['interval_months'] === 6
                        && $filter !== null
                        && $filter['interval_km'] === 12000
                        && $filter['interval_months'] === 12
                        && $air !== null
                        && $air['interval_km'] === 12000
                        && $cards->firstWhere('name', 'Chain clean and lube') === null;
                }));
    }

    public function test_an_oil_change_can_be_logged_with_odometer_and_spend(): void
    {
        $this->seed(LedgerSeeder::class);

        $vehicle = Vehicle::query()->firstOrFail();
        $oil = MaintenancePart::query()->where('slug', 'engine-oil')->firstOrFail();

        $this->actingAs(User::owner())
            ->post("/vehicles/{$vehicle->id}/logs", [
                'maintenance_part_id' => $oil->id,
                'serviced_on' => '2026-10-22',
                'odometer' => 18450,
                'cost' => 980,
                'shop' => 'Home garage',
                'log_as_expense' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('maintenance_logs', [
            'vehicle_id' => $vehicle->id,
            'odometer' => 18450,
        ]);
        $this->assertTrue(
            Transaction::query()->where('description', 'Engine oil · Z400')->where('amount', 980)->exists()
        );
        $this->assertSame(18450, $vehicle->fresh()->current_odometer);

        $log = MaintenanceLog::query()->where('odometer', 18450)->firstOrFail();
        $this->assertSame(24450, $log->next_due_odo);
    }
}
