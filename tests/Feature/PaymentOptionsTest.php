<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Wallets;
use App\Support\PayTag;
use Carbon\CarbonImmutable;
use Database\Seeders\LedgerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_cannot_be_edited_or_removed(): void
    {
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $wallets = app(Wallets::class);
        $cash = collect($wallets->all())->firstWhere('group', 'cash');

        $this->assertNotNull($cash);
        $this->assertTrue($cash['locked']);

        $this->post('/wallets', ['name' => 'Cash 2', 'group' => 'cash'])->assertSessionHasErrors('group');

        $this->put("/wallets/{$cash['id']}", ['name' => 'Paper'])->assertRedirect();
        $this->assertSame('Cash', collect($wallets->all())->firstWhere('id', $cash['id'])['name']);

        $this->delete("/wallets/{$cash['id']}")->assertRedirect();
        $this->assertNotNull(collect($wallets->all())->firstWhere('group', 'cash'));
    }

    public function test_credit_charge_spreads_to_bills_and_can_only_be_paid(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $wallets = app(Wallets::class);
        $this->post('/wallets', ['name' => 'Gotyme', 'group' => 'wallet'])->assertRedirect();
        $this->assertSame('wallet', collect($wallets->all())->firstWhere('name', 'Gotyme')['group']);

        $spay = collect($wallets->all())->firstWhere('slug', 'spay');
        $this->assertSame('credit', $spay['group']);

        $this->post("/wallets/{$spay['id']}/charges", [
            'description' => 'Headphones',
            'months' => 3,
            'monthly' => 1000,
            'first_due_on' => '2026-10-15',
        ])->assertRedirect();

        $october = $wallets->duesForPeriod(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );
        $this->assertCount(1, $october);
        $this->assertSame(1, $october[0]['n']);
        $this->assertSame(1000.0, $october[0]['amount']);

        $november = $wallets->duesForPeriod(
            CarbonImmutable::parse('2026-11-01'),
            CarbonImmutable::parse('2026-11-30'),
        );
        $this->assertTrue(collect($november)->contains(fn (array $due): bool => $due['n'] === 2 && $due['amount'] === 1000.0));

        $this->get('/budget')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('spending_plan', 43510)
                ->where('wallets', fn ($rows) => collect($rows)->contains('name', 'Gotyme')));

        $this->get('/bills?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('credit_dues', fn ($rows) => collect($rows)->contains(
                    fn ($due) => ($due['description'] ?? null) === 'Headphones' && ($due['payable'] ?? false),
                )));

        $due = $october[0];

        $this->post('/pay', [
            'kind' => 'credit_due',
            'target_id' => $due['id'],
            'occurred_on' => '2026-10-15',
            'amount' => 1000,
            'payment_method' => 'spay',
        ])->assertStatus(422);

        $this->post('/pay', [
            'kind' => 'credit_due',
            'target_id' => $due['id'],
            'occurred_on' => '2026-10-15',
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertNotNull($wallets->findDue($due['id'])['due']['paid_on']);
        $this->assertTrue(
            Transaction::query()
                ->where('type', 'bill')
                ->where('description', 'like', '%Headphones%')
                ->where('amount', 1000)
                ->exists()
        );

        $this->get('/budget')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('spending_plan', 42510));
    }

    public function test_bill_and_log_can_be_paid_via_credit(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $bill = Category::query()->where('slug', 'meralco')->firstOrFail();

        $this->post('/pay', [
            'kind' => 'bill',
            'target_id' => $bill->id,
            'occurred_on' => '2026-10-15',
            'via_credit' => true,
            'payment_method' => 'spay',
            'credit_months' => 1,
            'credit_monthly' => 5650.64,
        ])->assertRedirect();

        $this->post('/log', [
            'occurred_on' => '2026-10-23',
            'items' => [[
                'description' => 'Shopee cable',
                'amount' => 199,
                'type' => 'daily',
                'via_credit' => true,
                'credit_method' => 'lazpay',
                'credit_months' => 3,
                'credit_monthly' => 70,
            ]],
        ])->assertRedirect();

        $wallets = app(Wallets::class);
        $october = collect($wallets->duesForPeriod(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        ));
        $november = collect($wallets->duesForPeriod(
            CarbonImmutable::parse('2026-11-01'),
            CarbonImmutable::parse('2026-11-30'),
        ));

        $this->assertFalse($october->contains(fn (array $due): bool => $due['description'] === 'Meralco'));
        $this->assertTrue($november->contains(fn (array $due): bool => $due['description'] === 'Meralco' && $due['amount'] === 5650.64 && $due['due_on'] === '2026-11-15'));
        $this->assertTrue($november->contains(fn (array $due): bool => $due['description'] === 'Shopee cable' && $due['n'] === 1 && $due['amount'] === 70.0 && $due['due_on'] === '2026-11-16'));

        $this->assertTrue(
            Transaction::query()->where('description', 'Meralco')->where('amount', 5650.64)->exists()
        );
        $this->assertTrue(
            Transaction::query()->where('description', 'Shopee cable')->where('amount', 199)->exists()
        );
    }

    public function test_credit_statement_hides_same_day_charges_already_in_the_total(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $meralco = Category::query()->where('slug', 'meralco')->firstOrFail();
        $maynilad = Category::query()->where('slug', 'maynilad')->firstOrFail();
        $globe = Category::query()->where('slug', 'globe')->firstOrFail();
        $spay = Category::query()->where('slug', 'spay')->firstOrFail();

        foreach ([
            [$meralco, 'spay', 1, 5650.64],
            [$maynilad, 'spay', 3, 360.69],
            [$globe, 'spay', 3, 570.80],
            [$spay, 'spay', 1, 7868.51],
        ] as [$bill, $method, $months, $monthly]) {
            $this->post('/pay', [
                'kind' => 'bill',
                'target_id' => $bill->id,
                'occurred_on' => '2026-10-15',
                'via_credit' => true,
                'payment_method' => $method,
                'credit_months' => $months,
                'credit_monthly' => $monthly,
            ])->assertRedirect();
        }

        $wallets = app(Wallets::class);
        $october = collect($wallets->duesForPeriod(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        ));
        $november = collect($wallets->duesForPeriod(
            CarbonImmutable::parse('2026-11-01'),
            CarbonImmutable::parse('2026-11-30'),
        ));
        $december = collect($wallets->duesForPeriod(
            CarbonImmutable::parse('2026-12-01'),
            CarbonImmutable::parse('2026-12-31'),
        ));

        $this->assertSame([], $october->pluck('description')->all());
        $this->assertSame(['Spay'], $november->pluck('description')->all());
        $this->assertSame([7868.51], $november->pluck('amount')->all());
        $this->assertTrue($december->contains(fn (array $due): bool => $due['description'] === 'Maynilad' && $due['n'] === 2));
        $this->assertTrue($december->contains(fn (array $due): bool => $due['description'] === 'Globe' && $due['n'] === 2));
        $this->assertFalse($november->contains(fn (array $due): bool => $due['description'] === 'Meralco'));
        $this->assertSame(0, $wallets->unpaidDueCentsForMonth(2026, 10));
        $this->assertSame(786851, $wallets->unpaidDueCentsForMonth(2026, 11));

        $this->get('/bills?year=2026&month=11')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pay_on', '2026-11-15')
                ->where('credit_dues', fn ($rows) => collect($rows)->pluck('description')->all() === ['Spay'])
                ->where('computed_bills', fn ($rows) => collect($rows)->contains(
                    fn ($row) => ($row['slug'] ?? null) === 'spay'
                        && (float) $row['plan'] === 7868.51
                        && ($row['paid'] ?? true) === false,
                ) && collect($rows)->contains(fn ($row) => ($row['slug'] ?? null) === 'lazpay')));
    }

    public function test_a_future_month_still_lists_monthly_loans_and_credit_bills(): void
    {
        $this->travelTo('2026-10-24');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $this->get('/bills?year=2026&month=11')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('pay_on', '2026-11-15')
                ->where('totals.loan', 15050)
                ->where('totals.bills', 10460)
                ->where('computed_bills', fn ($rows) => collect($rows)->pluck('slug')->sort()->values()->all() === ['lazpay', 'spay'])
                ->where('loans', fn ($rows) => collect($rows)->contains(
                    fn ($loan) => $loan['name'] === 'iPhone 16'
                        && ($loan['due_this_period'] ?? false) === true
                        && ($loan['paid_this_period'] ?? true) === false
                        && (float) $loan['installment_amount'] === 2000.0,
                ) && collect($rows)->contains(
                    fn ($loan) => $loan['name'] === 'iPhone 17' && ($loan['due_this_period'] ?? false) === true,
                ) && collect($rows)->contains(
                    fn ($loan) => $loan['name'] === 'Security Bank credit card' && ($loan['due_this_period'] ?? false) === true,
                ) && collect($rows)->contains(
                    fn ($loan) => $loan['name'] === 'Personal loan' && ($loan['due_this_period'] ?? true) === false,
                )));

        $this->get('/bills?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('computed_bills', fn ($rows) => collect($rows)->contains(
                    fn ($row) => ($row['slug'] ?? null) === 'spay'
                        && ($row['paid'] ?? false) === true
                        && (float) $row['spent'] === 7550.0,
                ) && collect($rows)->contains(
                    fn ($row) => ($row['slug'] ?? null) === 'lazpay'
                        && ($row['paid'] ?? false) === true
                        && (float) $row['spent'] === 870.0,
                ))
                ->where('loans', fn ($rows) => collect($rows)->contains(
                    fn ($loan) => $loan['name'] === 'iPhone 16' && ($loan['paid_this_period'] ?? false) === true,
                ) && collect($rows)->contains(
                    fn ($loan) => $loan['name'] === 'iPhone 17' && ($loan['paid_this_period'] ?? false) === true,
                ) && collect($rows)->contains(
                    fn ($loan) => $loan['name'] === 'Security Bank credit card' && ($loan['paid_this_period'] ?? false) === true,
                )));

        $this->get('/?year=2026&month=11')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('figures.view_bills', 10460)
                ->where('figures.view_loan', 15050)
                ->where('figures.view_daily', 17000)
                ->where('dues', fn ($rows) => collect($rows)->contains('name', 'iPhone 16')
                    && collect($rows)->contains('name', 'iPhone 17')
                    && collect($rows)->contains('name', 'Security Bank credit card')
                    && collect($rows)->contains('name', 'SPay')
                    && collect($rows)->contains('name', 'LazPay')
                    && collect($rows)->doesntContain('name', 'Personal loan')));
    }

    public function test_a_credit_charge_on_or_after_due_day_bills_next_cycle(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $wallets = app(Wallets::class);
        $spay = collect($wallets->all())->firstWhere('slug', 'spay');
        $lazpay = collect($wallets->all())->firstWhere('slug', 'lazpay');

        $this->assertSame('2026-10-15', $wallets->firstDueOn($spay, '2026-10-14'));
        $this->assertSame('2026-11-15', $wallets->firstDueOn($spay, '2026-10-15'));
        $this->assertSame('2026-10-16', $wallets->firstDueOn($lazpay, '2026-10-15'));
        $this->assertSame('2026-11-16', $wallets->firstDueOn($lazpay, '2026-10-16'));
    }

    public function test_credit_accounts_are_not_plan_bills(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $this->get('/budget')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('computed_bills', [])
                ->where('bills', fn ($rows) => collect($rows)->doesntContain('slug', 'spay')
                    && collect($rows)->doesntContain('slug', 'lazpay')));

        $meralco = Category::query()->where('slug', 'meralco')->firstOrFail();

        $this->post('/pay', [
            'kind' => 'bill',
            'target_id' => $meralco->id,
            'occurred_on' => '2026-10-14',
            'via_credit' => true,
            'payment_method' => 'spay',
            'credit_months' => 1,
            'credit_monthly' => 5650.64,
        ])->assertRedirect();

        $this->post('/log', [
            'occurred_on' => '2026-10-10',
            'items' => [[
                'description' => 'Shopee cable',
                'amount' => 199,
                'type' => 'daily',
                'via_credit' => true,
                'credit_method' => 'lazpay',
                'credit_months' => 1,
                'credit_monthly' => 199,
            ]],
        ])->assertRedirect();

        $this->get('/budget')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bills', fn ($rows) => collect($rows)->doesntContain('slug', 'spay')
                    && collect($rows)->doesntContain('slug', 'lazpay'))
                ->where('computed_bills', fn ($rows) => collect($rows)->contains(
                    fn ($row) => ($row['slug'] ?? null) === 'spay' && (float) $row['monthly_plan'] === 5650.64,
                ) && collect($rows)->contains(
                    fn ($row) => ($row['slug'] ?? null) === 'lazpay' && (float) $row['monthly_plan'] === 199.0,
                )));

        $this->get('/bills?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bills', fn ($rows) => collect($rows)->doesntContain('slug', 'spay')
                    && collect($rows)->doesntContain('slug', 'lazpay'))
                ->where('credit_dues', fn ($rows) => collect($rows)->contains('description', 'Meralco')
                    && collect($rows)->contains('description', 'Shopee cable')));

        $this->post('/bills', [
            'name' => 'SPay',
            'monthly_plan' => 0,
        ])->assertSessionHasErrors('name');

        $this->post('/bills', [
            'name' => 'LazPay',
            'monthly_plan' => 0,
        ])->assertSessionHasErrors('name');
    }

    public function test_a_paid_bill_is_marked_paid_and_can_be_edited(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $cursor = Category::query()->where('slug', 'cursor')->firstOrFail();
        $payment = Transaction::query()
            ->where('category_id', $cursor->id)
            ->where('type', 'bill')
            ->whereYear('occurred_on', 2026)
            ->whereMonth('occurred_on', 10)
            ->firstOrFail();

        $this->get('/bills?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bills', fn ($rows) => collect($rows)->contains(
                    fn ($bill) => ($bill['slug'] ?? null) === 'cursor'
                        && ($bill['paid'] ?? false) === true
                        && ($bill['payment']['id'] ?? null) === $payment->id,
                ) && collect($rows)->contains(
                    fn ($bill) => ($bill['slug'] ?? null) === 'meralco'
                        && ($bill['paid'] ?? true) === false,
                )));

        $this->put("/pay/{$payment->id}", [
            'kind' => 'bill',
            'target_id' => $cursor->id,
            'occurred_on' => '2026-10-02',
            'amount' => 1300,
            'payment_method' => 'cash',
            'notes' => 'Adjusted',
        ])->assertRedirect();

        $fresh = $payment->fresh();
        $this->assertNotNull($fresh);
        $this->assertEquals(1300, (float) $fresh->amount);
        $this->assertSame('2026-10-02', $fresh->occurred_on->toDateString());
        $this->assertSame('cash', PayTag::slug($fresh->notes));
        $this->assertSame('Adjusted', PayTag::strip($fresh->notes));
    }

    public function test_paid_cash_bills_use_actual_amount_not_the_budget(): void
    {
        $this->travelTo('2026-10-24');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $meralco = Category::query()->where('slug', 'meralco')->firstOrFail();
        $maynilad = Category::query()->where('slug', 'maynilad')->firstOrFail();
        $globe = Category::query()->where('slug', 'globe')->firstOrFail();

        foreach ([
            [$meralco, 1, 5650.64],
            [$maynilad, 3, 360.69],
            [$globe, 3, 570.80],
        ] as [$bill, $months, $monthly]) {
            $this->post('/pay', [
                'kind' => 'bill',
                'target_id' => $bill->id,
                'occurred_on' => '2026-10-16',
                'via_credit' => true,
                'payment_method' => 'spay',
                'credit_months' => $months,
                'credit_monthly' => $monthly,
            ])->assertRedirect();
        }

        $this->get('/bills?year=2026&month=10')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('totals.bills', 9705.11)
                ->where('bills', fn ($rows) => collect($rows)->contains(
                    fn ($bill) => ($bill['slug'] ?? null) === 'meralco'
                        && (float) $bill['spent'] === 5650.64
                        && (float) $bill['plan'] === 6000.0,
                ) && collect($rows)->contains(
                    fn ($bill) => ($bill['slug'] ?? null) === 'maynilad'
                        && (float) $bill['spent'] === 1082.07
                        && (float) $bill['plan'] === 1500.0,
                ) && collect($rows)->contains(
                    fn ($bill) => ($bill['slug'] ?? null) === 'globe'
                        && (float) $bill['spent'] === 1712.4
                        && (float) $bill['plan'] === 1700.0,
                )));

        $this->get('/bills?year=2026&month=11')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('bills', fn ($rows) => collect($rows)->contains(
                    fn ($bill) => ($bill['slug'] ?? null) === 'meralco'
                        && ($bill['paid'] ?? true) === false
                        && (float) $bill['spent'] === 0.0
                        && (float) $bill['plan'] === 6000.0,
                ) && collect($rows)->contains(
                    fn ($bill) => ($bill['slug'] ?? null) === 'maynilad'
                        && ($bill['paid'] ?? true) === false
                        && (float) $bill['plan'] === 1500.0,
                ) && collect($rows)->contains(
                    fn ($bill) => ($bill['slug'] ?? null) === 'globe'
                        && ($bill['paid'] ?? true) === false
                        && (float) $bill['plan'] === 1700.0,
                )));
    }

    public function test_an_unpaid_due_can_be_dropped_without_later_months(): void
    {
        $this->travelTo('2026-10-22');
        $this->seed(LedgerSeeder::class);
        $this->actingAs(User::owner());

        $wallets = app(Wallets::class);
        $spay = collect($wallets->all())->firstWhere('slug', 'spay');

        $this->post("/wallets/{$spay['id']}/charges", [
            'description' => 'Maynilad',
            'months' => 3,
            'monthly' => 360.69,
            'first_due_on' => '2026-10-15',
        ])->assertRedirect();

        $october = $wallets->duesForPeriod(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );
        $wallets->dropUnpaidDue($october[0]['id']);

        $this->assertCount(0, $wallets->duesForPeriod(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        ));
        $this->assertTrue(collect($wallets->duesForPeriod(
            CarbonImmutable::parse('2026-11-01'),
            CarbonImmutable::parse('2026-11-30'),
        ))->contains(fn (array $due): bool => $due['description'] === 'Maynilad' && $due['n'] === 2));
    }
}
