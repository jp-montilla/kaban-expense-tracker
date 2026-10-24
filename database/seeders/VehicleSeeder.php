<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\BikeParts;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('email', User::OWNER_EMAIL)->firstOrFail();

        Category::query()->updateOrCreate(
            ['user_id' => $owner->id, 'slug' => 'z400'],
            [
                'name' => 'Z400',
                'kind' => 'variable',
                'color' => '#3a4a38',
                'monthly_plan' => 0,
                'sort_order' => 165,
                'is_active' => true,
            ],
        );

        $bike = Vehicle::query()->updateOrCreate(
            ['user_id' => $owner->id, 'name' => 'Z400'],
            [
                'make' => 'Kawasaki',
                'model' => 'Z400',
                'year' => 2020,
                'current_odometer' => 0,
                'notes' => 'Big bike. Log oil, filters, chain, brakes, and the odo at each service.',
            ],
        );

        $ids = BikeParts::seed($bike);

        $history = [
            ['2025-10-10', 'engine-oil', 12500, 'Clutch and Oil Change'],
            ['2025-10-10', 'clutch', null, 'Clutch and Oil Change'],
            ['2026-04-10', 'tires', 6400, 'Change Tires'],
            ['2026-04-14', 'engine-oil', null, 'Change Oil, oil filter'],
            ['2026-04-14', 'oil-filter', null, 'Change Oil, oil filter'],
            ['2026-06-20', 'chain-sprocket', 1000, 'Chain and sprocket'],
        ];

        $keep = [];

        foreach ($history as [$date, $slug, $cost, $notes]) {
            $log = $bike->logs()->updateOrCreate(
                [
                    'maintenance_part_id' => $ids[$slug],
                    'serviced_on' => $date,
                ],
                [
                    'cost' => $cost,
                    'odometer' => null,
                    'shop' => null,
                    'transaction_id' => null,
                    'notes' => $notes,
                ],
            );
            $keep[] = $log->id;
        }

        $bike->logs()->whereNotIn('id', $keep)->get()->each(function ($log): void {
            $log->transaction?->delete();
            $log->delete();
        });
    }
}
