<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Debt;
use App\Models\Salary;
use App\Models\SavingsEntry;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\LedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_october_2026_matches_the_summary_sheet(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);

        $this->post('/login', [
            'email' => User::OWNER_EMAIL,
            'password' => User::OWNER_PASSWORD,
        ])->assertRedirect('/');

        $this->get('/?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('figures.income', 85000)
                ->where('figures.latest_income', 55000)
                ->where('figures.first_cutoff', 30000)
                ->where('figures.second_cutoff', 55000)
                ->where('figures.month_actual', 47619)
                ->where('figures.cash_left', 37381)
                ->where('figures.spending_plan', 42510)
                ->where('figures.view_bills', 10460)
                ->where('figures.view_loan', 15050)
                ->where('figures.view_daily', 17000)
                ->where('figures.view_savings', 0)
                ->where('figures.savings_balance', 44000)
                ->where('period.days_left', 10)
                ->where('figures.variable_plan', 17000)
                ->where('logCategories', function ($rows) {
                    $rows = collect($rows);

                    return $rows->count() === 1 && $rows[0]['slug'] === 'daily' && $rows[0]['name'] === 'Living';
                })
                ->where('top_spends.all', fn ($rows) => $rows->contains('name', 'Living') && $rows->contains('name', 'Meralco')));

        $this->get('/?year=2026&month=10&half=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('figures.income', 30000)
                ->where('figures.cutoff_pay', 30000));

        $this->get('/?year=2026&month=10&half=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('figures.income', 55000)
                ->where('figures.cutoff_pay', 55000));

        $this->get('/summary?year=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('months.3.cash_left', 645)
                ->where('months.11.cash_left', -17421));

        $this->get('/?year=2026&month=11')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('figures.income', 110000)
                ->where('figures.first_cutoff', 55000)
                ->where('figures.second_cutoff', 55000)
                ->where('figures.pay_mode', 'cutoff')
                ->where('figures.spending_plan', 42510)
                ->where('figures.view_bills', 10460)
                ->where('figures.view_loan', 15050)
                ->where('figures.view_daily', 17000)
                ->where('figures.cash_left', 110000));

        $this->get('/budget')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('budget_month', 'October 2026')
                ->where('previous_pay_label', 'September 30')
                ->where('current_pay_label', 'October 15')
                ->where('month_pay', 85000)
                ->where('first_cutoff', 30000)
                ->where('second_cutoff', 55000)
                ->where('pay_mode', 'cutoff')
                ->where('spending_plan', 42510)
                ->where('living_plan', 17000)
                ->where('left_if_followed', 42490)
                ->has('salaries', 3)
                ->has('bills')
                ->has('wallets'));

        $this->get('/loans')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('debts', function ($debts) {
                    $balances = $debts->pluck('balance', 'name');
                    $sb = $debts->firstWhere('name', 'Security Bank credit card');

                    $iphone = $debts->firstWhere('name', 'iPhone 17');
                    $iphone16 = $debts->firstWhere('name', 'iPhone 16');

                    return $balances['Personal loan'] == 25000
                        && $iphone !== null
                        && $iphone['is_monthly'] === true
                        && $iphone['installment_count'] === 12
                        && $iphone['installments_paid'] === 10
                        && $iphone['installments_remaining'] === 2
                        && $iphone['balance'] == 12800
                        && $iphone16 !== null
                        && $iphone16['is_monthly'] === true
                        && $iphone16['installment_count'] === 24
                        && $iphone16['installments_paid'] === 6
                        && $iphone16['installments_remaining'] === 18
                        && $iphone16['balance'] == 36000
                        && $iphone16['installment_amount'] == 2000
                        && $sb !== null
                        && $sb['installments_remaining'] === 43
                        && $sb['installment_amount'] == 6650
                        && $sb['balance'] == 285950;
                }));
    }

    public function test_the_savings_page_lists_entries_and_deducts_budget_left(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $this->get('/savings?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Savings/Index')
                ->where('entries', function ($rows) {
                    return $rows->contains(fn ($row) => ($row['notes'] ?? null) === 'Starting savings'
                        && $row['amount'] == 44000
                        && $row['opening'] === true);
                })
                ->where('cash_left', 37381)
                ->where('balance', 44000));

        $this->post('/savings', [
            'occurred_on' => '2026-10-22',
            'amount' => 12490,
            'notes' => 'Leftover pay',
        ])->assertRedirect();

        $this->assertTrue(
            SavingsEntry::query()
                ->whereDate('occurred_on', '2026-10-22')
                ->where('amount', 12490)
                ->where('direction', 'in')
                ->exists()
        );

        $this->get('/?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('figures.cash_left', 24891)
                ->where('figures.view_savings', 12490)
                ->where('figures.savings_balance', 56490));

        $this->get('/savings?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_saved', 12490)
                ->where('cash_left', 24891)
                ->where('balance', 56490));
    }

    public function test_a_savings_entry_can_be_updated(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $this->post('/savings', [
            'occurred_on' => '2026-10-22',
            'amount' => 5000,
            'notes' => 'Leftover pay',
        ])->assertRedirect();

        $entry = SavingsEntry::query()->where('notes', 'Leftover pay')->firstOrFail();

        $this->put("/savings/{$entry->id}", [
            'occurred_on' => '2026-10-22',
            'amount' => 8000,
            'notes' => 'Leftover pay',
        ])->assertRedirect();

        $this->assertSame('8000.00', $entry->fresh()?->amount);

        $this->get('/savings?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('month_saved', 8000)
                ->where('cash_left', 29381)
                ->where('balance', 52000));
    }

    public function test_savings_all_lists_every_date(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        SavingsEntry::query()->create([
            'occurred_on' => '2026-08-31',
            'amount' => 13000,
            'direction' => 'in',
            'notes' => 'August leftover',
        ]);

        $this->get('/savings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.span', 'all')
                ->where('balance', 57000)
                ->where('entries', function ($rows) {
                    return $rows->contains(fn ($row) => ($row['notes'] ?? null) === 'Starting savings')
                        && $rows->contains(fn ($row) => ($row['occurred_on'] ?? null) === '2026-08-31'
                            && $row['amount'] == 13000);
                }));
    }

    public function test_a_daily_expense_can_be_logged_on_any_date(): void
    {
        $this->seed(LedgerSeeder::class);

        $category = Category::query()->where('slug', 'daily')->firstOrFail();

        $this->actingAs(User::owner())
            ->get('/log?year=2026&month=10&half=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.span', 'month')
                ->where('period.half', null)
                ->where('period.label', 'October 2026')
                ->where('categories', function ($rows) {
                    $rows = collect($rows);

                    return $rows->count() === 1 && $rows[0]['slug'] === 'daily';
                })
                ->where('transactions', function ($rows) {
                    $dates = collect($rows)->pluck('occurred_on');

                    return $dates->contains('2026-10-01')
                        && $dates->contains('2026-10-16')
                        && $dates->contains('2026-10-21');
                }));

        $this->get('/?year=2026&month=10&half=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('recent', function ($rows) {
                    return collect($rows)->contains(fn ($row) => ($row['occurred_on'] ?? null) === '2026-10-21');
                }));

        $this->actingAs(User::owner())
            ->post('/transactions', [
                'occurred_on' => '2026-10-22',
                'description' => 'Taho',
                'amount' => 40,
                'direction' => 'out',
                'type' => 'daily',
                'category_id' => $category->id,
            ])
            ->assertRedirect();

        $this->assertTrue(
            Transaction::query()
                ->where('description', 'Taho')
                ->whereDate('occurred_on', '2026-10-22')
                ->where('amount', 40)
                ->exists()
        );
    }

    public function test_daily_items_append_on_the_same_day(): void
    {
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $this->post('/log', [
            'occurred_on' => '2026-10-23',
            'items' => [
                ['description' => 'Taho', 'amount' => 40, 'type' => 'daily'],
            ],
        ])->assertRedirect();

        $this->post('/log', [
            'occurred_on' => '2026-10-23',
            'items' => [
                ['description' => 'Gas', 'amount' => 500, 'type' => 'daily'],
            ],
        ])->assertRedirect();

        $this->assertSame(2, Transaction::query()->whereDate('occurred_on', '2026-10-23')->whereIn('type', ['daily', 'refund'])->count());

        $taho = Transaction::query()->where('description', 'Taho')->whereDate('occurred_on', '2026-10-23')->firstOrFail();

        $this->put('/log/2026-10-23', [
            'occurred_on' => '2026-10-23',
            'items' => [
                ['id' => $taho->id, 'description' => 'Taho', 'amount' => 40, 'type' => 'daily'],
                ['description' => 'Grocery', 'amount' => 200, 'type' => 'daily'],
            ],
        ])->assertRedirect();

        $this->assertTrue(Transaction::query()->where('description', 'Grocery')->whereDate('occurred_on', '2026-10-23')->where('amount', 200)->exists());
        $this->assertFalse(Transaction::query()->where('description', 'Gas')->whereDate('occurred_on', '2026-10-23')->exists());
        $this->assertSame(2, Transaction::query()->whereDate('occurred_on', '2026-10-23')->whereIn('type', ['daily', 'refund'])->count());
    }

    public function test_plan_pay_and_bills_can_be_edited(): void
    {
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $salary = Salary::query()->whereDate('effective_on', '2026-10-16')->firstOrFail();

        $this->put("/salaries/{$salary->id}", [
            'effective_on' => '2026-10-16',
            'amount' => 55000,
            'pay_cycle' => 'cutoff',
            'notes' => 'Updated raise',
        ])->assertRedirect();

        $this->assertSame('Updated raise', $salary->fresh()->noteText());

        $bill = Category::query()->where('kind', 'bill')->where('is_active', true)->orderBy('name')->firstOrFail();

        $this->put("/bills/{$bill->id}", [
            'name' => $bill->name,
            'monthly_plan' => 1234,
        ])->assertRedirect();

        $this->assertEquals(1234, (float) $bill->fresh()->monthly_plan);

        $this->post('/wallets', ['name' => 'UnionBank'])->assertRedirect();
        $this->get('/budget')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('wallets', fn ($rows) => collect($rows)->contains('name', 'UnionBank')));
    }

    public function test_loan_can_be_monthly_or_personal(): void
    {
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $this->post('/debts', [
            'name' => 'Camera',
            'creditor' => 'SM',
            'item' => 'Sony A7',
            'is_monthly' => true,
            'installment_amount' => 1000,
            'installment_count' => 12,
            'notes' => 'Store promo',
        ])->assertRedirect();

        $monthly = Debt::query()->where('name', 'Camera')->firstOrFail();
        $this->assertSame('Sony A7', $monthly->item);
        $this->assertSame('SM', $monthly->creditor);
        $this->assertSame(12, $monthly->installment_count);
        $this->assertSame('1000.00', $monthly->installment_amount);
        $this->assertSame('12000.00', $monthly->principal);

        $this->post('/debts', [
            'name' => 'Personal',
            'item' => 'Cash',
            'principal' => 5000,
            'is_monthly' => false,
            'installment_amount' => 999,
            'installment_count' => 9,
        ])->assertRedirect();

        $personal = Debt::query()->where('name', 'Personal')->firstOrFail();
        $this->assertSame('Cash', $personal->item);
        $this->assertNull($personal->installment_count);
        $this->assertNull($personal->installment_amount);
        $this->assertSame('5000.00', $personal->principal);
    }
}
