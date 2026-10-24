<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\SavingsEntry;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class LedgerSeeder extends Seeder
{
    private User $owner;

    public function run(): void
    {
        $owner = User::query()
            ->whereIn('email', [User::OWNER_EMAIL, 'kaban@localhost'])
            ->orderByRaw('email = ? desc', [User::OWNER_EMAIL])
            ->first();

        $values = [
            'email' => User::OWNER_EMAIL,
            'name' => 'John Paul',
            'password' => User::OWNER_PASSWORD,
            'role' => User::ROLE_SUPERADMIN,
            'is_active' => true,
            'living_plan' => 17000,
        ];

        $this->owner = $owner
            ? tap($owner, fn (User $user) => $user->update($values))
            : User::query()->create($values);

        $categories = $this->seedCategories();
        Setting::putValue(Setting::LIVING_PLAN, '17000');
        $this->call(SalarySeeder::class);
        $this->call(VehicleSeeder::class);
        $this->seedSavings();
        $this->seedBills($categories);
        $this->seedDaily($categories);
        $this->seedDebts($categories);
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $rows = require __DIR__.'/data/categories.php';
        $categories = [];

        foreach ($rows as $index => $row) {
            $categories[$row['slug']] = Category::query()->updateOrCreate(
                ['user_id' => $this->owner->id, 'slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'kind' => $row['kind'],
                    'color' => $row['color'],
                    'monthly_plan' => $row['plan'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => $row['active'] ?? true,
                ],
            );
        }

        $plan = collect($rows)
            ->filter(fn (array $row): bool => in_array($row['kind'], ['bill', 'debt'], true) && ($row['active'] ?? true))
            ->sum('plan') + 17000;

        if (abs($plan - 42510) > 0.01) {
            throw new RuntimeException("Spending plan is {$plan}, expected 42510.");
        }

        return $categories;
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function seedBills(array $categories): void
    {
        foreach (require __DIR__.'/data/bills.php' as $row) {
            $sum = array_sum(array_column($row['lines'], 1));

            if (abs($sum - $row['total']) > 0.05) {
                throw new RuntimeException("Bills on {$row['date']} sum to {$sum}, sheet says {$row['total']}.");
            }

            $date = CarbonImmutable::parse($row['date']);
            $last = $date->day <= 15 ? 15 : $date->daysInMonth;
            $start = $date->day <= 15 ? 1 : 16;
            $label = $date->format('M').' '.$start.'–'.$last;

            foreach ($row['lines'] as [$slug, $amount]) {
                if ($slug === 'savings' || abs($amount) < 0.005) {
                    continue;
                }

                $category = $categories[$slug] ?? throw new RuntimeException("Unknown category {$slug}.");

                Transaction::query()->create([
                    'user_id' => $this->owner->id,
                    'occurred_on' => $row['date'],
                    'amount' => abs($amount),
                    'direction' => 'out',
                    'type' => 'bill',
                    'category_id' => $category->id,
                    'description' => $category->name.' · '.$label,
                    'notes' => 'From the monthly bills sheet.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function seedDaily(array $categories): void
    {
        $daily = $categories['daily'];
        $nets = [];

        foreach (require __DIR__.'/data/daily.php' as [$date, $description, $amount]) {
            $month = substr($date, 0, 7);
            $nets[$month] = ($nets[$month] ?? 0) + $amount;

            Transaction::query()->create([
                'user_id' => $this->owner->id,
                'occurred_on' => $date,
                'amount' => abs($amount),
                'direction' => $amount < 0 ? 'in' : 'out',
                'type' => $amount < 0 ? 'refund' : 'daily',
                'category_id' => $daily->id,
                'description' => $description,
            ]);
        }

        $expected = [
            '2026-08' => 20835,
            '2026-09' => 30260,
            '2026-10' => 20889,
        ];

        foreach ($expected as $month => $total) {
            if (abs(($nets[$month] ?? 0) - $total) > 0.05) {
                throw new RuntimeException("Daily log for {$month} sums to ".($nets[$month] ?? 0).", sheet says {$total}.");
            }
        }

        $lumps = [
            ['2026-01-02', 32113, 'Daily expenses · Jan 1–15'],
            ['2026-01-17', 8409, 'Daily expenses · Jan 16–31'],
            ['2026-02-02', 11814, 'Daily expenses · Feb 1–15'],
            ['2026-02-17', 12281, 'Daily expenses · Feb 16–28'],
            ['2026-03-02', 20599, 'Daily expenses · Mar 1–15'],
            ['2026-03-17', 22172, 'Daily expenses · Mar 16–31'],
            ['2026-04-02', 38650, 'Daily expenses · Apr 1–15'],
            ['2026-04-17', 16882, 'Daily expenses · Apr 16–30'],
            ['2026-05-02', 23512, 'Daily expenses · May 1–15'],
            ['2026-05-17', 7871, 'Daily expenses · May 16–30'],
            ['2026-06-02', 26617, 'Daily expenses · Jun 1–15'],
            ['2026-06-17', 14540, 'Daily expenses · Jun 16–30'],
            ['2026-07-02', 17799, 'Daily expenses · Jul 1–15'],
            ['2026-07-17', 8520, 'Daily expenses · Jul 16–31'],
        ];

        foreach ($lumps as [$date, $amount, $description]) {
            Transaction::query()->create([
                'user_id' => $this->owner->id,
                'occurred_on' => $date,
                'amount' => $amount,
                'direction' => 'out',
                'type' => 'daily',
                'category_id' => $daily->id,
                'description' => $description,
                'notes' => 'Imported total from the summary sheet. Delete it and log the real lines whenever you want the detail.',
            ]);
        }
    }

    /**
     * @param  array<string, Category>  $categories
     */
    private function seedDebts(array $categories): void
    {
        Debt::query()->create([
            'user_id' => $this->owner->id,
            'category_id' => $categories['iphone-17']->id,
            'name' => 'iPhone 17',
            'creditor' => 'A friend',
            'item' => 'iPhone 17',
            'principal' => 76800,
            'opening_paid' => 64000,
            'installment_amount' => 6400,
            'installment_count' => 12,
            'status' => 'active',
            'notes' => '₱6,400 a month for 12 months. 10 paid, two left: Nov 15 and Dec 15. Amount is already on the bills sheet, so remaining dates are a checklist.',
        ]);

        Debt::query()->create([
            'user_id' => $this->owner->id,
            'category_id' => $categories['personal-utang']->id,
            'name' => 'Personal loan',
            'creditor' => 'A friend',
            'item' => 'Cash',
            'principal' => 30000,
            'opening_paid' => 5000,
            'status' => 'active',
            'notes' => '₱30,000 borrowed. ₱5,000 was already paid when this was written down, with no date on the sheet. ₱25,000 is still open.',
        ]);

        $iphone16 = Debt::query()->create([
            'user_id' => $this->owner->id,
            'category_id' => $categories['iphone-16']->id,
            'name' => 'iPhone 16',
            'creditor' => null,
            'item' => 'iPhone 16',
            'principal' => 48000,
            'opening_paid' => 0,
            'installment_amount' => 2000,
            'installment_count' => 24,
            'status' => 'active',
            'notes' => '₱2,000 a month for 24 months. 6 paid, 18 left. Dates are a checklist because the amounts are already on the bills sheet.',
        ]);

        $iphone16Paid = [
            ['2026-05-15', 1],
            ['2026-06-18', 2],
            ['2026-07-15', 3],
            ['2026-08-16', 4],
            ['2026-09-23', 5],
            ['2026-10-20', 6],
        ];

        foreach ($iphone16Paid as [$date, $number]) {
            DebtPayment::query()->create([
                'debt_id' => $iphone16->id,
                'occurred_on' => $date,
                'amount' => 2000,
                'installment_no' => $number,
                'notes' => 'Marked paid.',
            ]);
        }

        Debt::query()->create([
            'user_id' => $this->owner->id,
            'category_id' => $categories['sb-cc']->id,
            'name' => 'Security Bank credit card',
            'creditor' => 'Security Bank',
            'item' => 'Credit card',
            'principal' => 399000,
            'opening_paid' => 113050,
            'installment_amount' => 6650,
            'installment_count' => 60,
            'status' => 'active',
            'notes' => 'Consolidated card debt. ₱6,650 a month for 60 months. 17 months are already paid, 43 left. The monthly amount is already on the bills sheet, so this is the remaining balance, not a second charge.',
        ]);
    }

    private function seedSavings(): void
    {
        SavingsEntry::query()->updateOrCreate(
            [
                'user_id' => $this->owner->id,
                'occurred_on' => '2026-10-01',
                'notes' => 'Starting savings',
            ],
            [
                'amount' => 44000,
                'direction' => 'in',
            ],
        );
    }
}
