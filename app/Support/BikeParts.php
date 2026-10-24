<?php

namespace App\Support;

use App\Models\MaintenancePart;
use App\Models\Vehicle;
use Illuminate\Support\Str;

class BikeParts
{
    /**
     * @return list<array{name: string, slug: string, km: int|null, months: int|null, order: int}>
     */
    public static function catalog(): array
    {
        return [
            ['name' => 'Engine oil', 'slug' => 'engine-oil', 'km' => 6000, 'months' => 6, 'order' => 10],
            ['name' => 'Oil filter', 'slug' => 'oil-filter', 'km' => 12000, 'months' => 12, 'order' => 20],
            ['name' => 'Air filter', 'slug' => 'air-filter', 'km' => 12000, 'months' => 12, 'order' => 30],
            ['name' => 'Spark plugs', 'slug' => 'spark-plugs', 'km' => 12000, 'months' => 12, 'order' => 40],
            ['name' => 'Chain and sprocket', 'slug' => 'chain-sprocket', 'km' => 15000, 'months' => 24, 'order' => 50],
            ['name' => 'Brake pads', 'slug' => 'brake-pads', 'km' => 10000, 'months' => 12, 'order' => 60],
            ['name' => 'Brake rotors', 'slug' => 'brake-rotors', 'km' => 20000, 'months' => 24, 'order' => 70],
            ['name' => 'Brake fluid', 'slug' => 'brake-fluid', 'km' => null, 'months' => 24, 'order' => 80],
            ['name' => 'Coolant', 'slug' => 'coolant', 'km' => 24000, 'months' => 24, 'order' => 90],
            ['name' => 'Tires', 'slug' => 'tires', 'km' => 15000, 'months' => 36, 'order' => 100],
            ['name' => 'Clutch', 'slug' => 'clutch', 'km' => 20000, 'months' => 36, 'order' => 110],
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function seed(Vehicle $vehicle): array
    {
        $ids = [];

        foreach (self::catalog() as $part) {
            $match = ['slug' => $part['slug']];
            $values = [
                'name' => $part['name'],
                'interval_km' => $part['km'],
                'interval_months' => $part['months'],
                'sort_order' => $part['order'],
            ];

            if (Ownership::hasPartVehicle()) {
                $match['vehicle_id'] = $vehicle->id;
            }

            if (Ownership::ready() && $vehicle->user_id) {
                $values['user_id'] = $vehicle->user_id;
            }

            $ids[$part['slug']] = MaintenancePart::query()->updateOrCreate($match, $values)->id;
        }

        return $ids;
    }

    public static function slugFor(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 'part';
    }
}
