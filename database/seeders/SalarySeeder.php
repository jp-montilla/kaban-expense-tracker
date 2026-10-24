<?php

namespace Database\Seeders;

use App\Models\Salary;
use App\Models\User;
use Illuminate\Database\Seeder;

class SalarySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('email', User::OWNER_EMAIL)->firstOrFail();

        Salary::query()
            ->where('user_id', $owner->id)
            ->whereDate('effective_on', '2026-10-01')
            ->forceDelete();

        $rows = [
            ['effective_on' => '2025-04-01', 'amount' => 27500, 'notes' => '₱27,500 per cutoff before the 60k raise.'],
            ['effective_on' => '2026-09-01', 'amount' => 30000, 'notes' => '₱30,000 per cutoff. Sep 30 pay covers Oct 1–15.'],
            ['effective_on' => '2026-10-16', 'amount' => 55000, 'notes' => '₱55,000. Each month is last month-end pay plus this month’s 15 pay. Oct is Sep 30 ₱30,000 then Oct 15 ₱55,000. Nov is Oct 30/31 ₱55,000 then Nov 15 ₱55,000.'],
        ];

        foreach ($rows as $row) {
            Salary::query()->updateOrCreate(
                [
                    'user_id' => $owner->id,
                    'effective_on' => $row['effective_on'],
                ],
                ['amount' => $row['amount'], 'notes' => $row['notes']],
            );
        }
    }
}
