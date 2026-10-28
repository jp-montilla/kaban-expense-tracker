<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\SavingsEntry;
use App\Models\Transaction;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MonthSheet
{
    public function __construct(
        private Wallets $wallets,
        private DueWhen $dueWhen,
        private LivingPlan $livingPlan,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function creditDues(Period $period): Collection
    {
        return collect($this->wallets->duesForPeriod($period->start, $period->end))
            ->filter(fn (array $due): bool => $this->dueWhen->matchesDate($due['due_on'] ?? null, $period->half))
            ->map(fn (array $due): array => [
                ...$due,
                'payable' => $due['paid_on'] === null,
            ])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $creditDues
     * @return Collection<int, array<string, mixed>>
     */
    public function computedBills(Period $period, ?Collection $creditDues = null): Collection
    {
        $creditDues ??= $this->creditDues($period);

        $categories = Category::query()
            ->where('kind', 'bill')
            ->get()
            ->filter(fn (Category $category): bool => $this->isSPayOrLazPay([
                'slug' => $category->slug,
                'name' => $category->name,
            ]));
        $sheetPays = $categories->isEmpty()
            ? collect()
            : Transaction::query()
                ->whereIn('category_id', $categories->pluck('id'))
                ->where('type', 'bill')
                ->whereDate('occurred_on', '>=', $period->start->toDateString())
                ->whereDate('occurred_on', '<=', $period->end->toDateString())
                ->orderByDesc('occurred_on')
                ->get()
                ->groupBy('category_id');

        return collect($this->wallets->credits())
            ->filter(fn (array $credit): bool => $this->isSPayOrLazPay($credit))
            ->map(function (array $credit) use ($creditDues, $categories, $sheetPays): array {
                $dues = $creditDues->where('wallet_id', $credit['id']);
                $duePlan = round((float) $dues->sum('amount'), 2);
                $dueSpent = round((float) $dues->whereNotNull('paid_on')->sum('amount'), 2);
                $category = $this->categoryForCredit($credit, $categories);
                $pays = $category === null
                    ? collect()
                    : ($sheetPays->get($category->id) ?? $sheetPays->get((string) $category->id) ?? collect());
                $sheetSpent = round((float) $pays->sum('amount'), 2);
                $latest = $pays->first();
                $item = $latest?->toListItem();
                $plan = $duePlan > 0 ? $duePlan : $sheetSpent;
                $spent = $dueSpent > 0 ? $dueSpent : $sheetSpent;

                return [
                    'id' => $credit['id'],
                    'name' => $credit['name'],
                    'slug' => $credit['slug'],
                    'category_id' => $category?->id,
                    'plan' => $plan,
                    'spent' => $spent,
                    'paid' => $spent > 0,
                    'payment' => $item === null ? null : [
                        'id' => $item['id'],
                        'occurred_on' => $item['occurred_on'],
                        'amount' => $item['amount'],
                        'payment_method' => $item['payment_method'],
                        'notes' => $item['notes'],
                        'via_credit' => $item['via_credit'],
                        'credit_months' => $item['credit_months'],
                        'credit_monthly' => $item['credit_monthly'],
                    ],
                ];
            })
            ->filter(function (array $row) use ($period, $creditDues): bool {
                if ($period->half === null) {
                    return true;
                }

                $dues = $creditDues->where('wallet_id', $row['id']);

                if ($dues->isNotEmpty()) {
                    return true;
                }

                $day = ($row['slug'] ?? '') === 'lazpay' ? 16 : 15;

                return $this->dueWhen->matchesHalf(['mode' => DueWhen::EXACT, 'day' => $day], $period->half);
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function loans(Period $period): Collection
    {
        $debts = Debt::query()
            ->with('payments')
            ->orderBy('status')
            ->orderBy('name')
            ->get();
        $categoryIds = $debts->pluck('category_id')->filter()->unique()->all();
        $sheetPays = $categoryIds === []
            ? collect()
            : Transaction::query()
                ->whereIn('category_id', $categoryIds)
                ->whereIn('type', ['bill', 'debt_payment'])
                ->orderBy('occurred_on')
                ->get()
                ->groupBy('category_id');
        $linked = $debts->flatMap(
            fn (Debt $debt): Collection => $debt->payments->pluck('transaction_id'),
        )->filter()->all();

        return $debts
            ->map(function (Debt $debt) use ($period, $sheetPays, $linked): array {
                $summary = $debt->toSummaryArray();
                $logged = $debt->spentIn($period->start, $period->end);
                $sheet = (float) ($sheetPays->get($debt->category_id) ?? collect())
                    ->filter(function (Transaction $row) use ($period, $linked): bool {
                        if (in_array($row->id, $linked, true)) {
                            return false;
                        }

                        $on = $row->occurred_on?->toImmutable()->startOfDay();

                        return $on !== null && $on->betweenIncluded($period->start->startOfDay(), $period->end->startOfDay());
                    })
                    ->sum(fn (Transaction $row): float => (float) $row->amount);
                $spent = round($logged > 0 ? $logged : $sheet, 2);
                $remaining = $summary['installments_remaining'];
                $open = $summary['status'] !== 'paid';
                $inWindow = $this->inRemainingWindow(
                    $period,
                    $this->lastPaidOn($debt, $sheetPays->get($debt->category_id) ?? collect()),
                    is_int($remaining) ? $remaining : null,
                );
                $due = $this->dueWhen->decorate('loans', $debt->id);
                $dueThisMonth = $debt->isMonthly() && $open && ($spent > 0 || $inWindow);

                return [
                    ...$summary,
                    'due_mode' => $due['mode'],
                    'due_day' => $due['day'],
                    'due_label' => $due['label'],
                    'due_this_period' => $dueThisMonth && $this->dueWhen->matchesHalf($due, $period->half),
                    'paid_this_period' => $spent > 0,
                    'spent_this_period' => $spent,
                ];
            })
            ->values();
    }

    /**
     * @return list<array{id: string, name: string, amount: float, paid: bool, kind: string}>
     */
    public function dues(Period $period): array
    {
        $creditDues = $this->creditDues($period);

        $loans = $this->loans($period)
            ->filter(fn (array $loan): bool => $loan['due_this_period'])
            ->map(fn (array $loan): array => [
                'id' => 'loan-'.$loan['id'],
                'name' => $loan['name'],
                'amount' => $loan['paid_this_period']
                    ? (float) $loan['spent_this_period']
                    : (float) ($loan['installment_amount'] ?? 0),
                'paid' => $loan['paid_this_period'],
                'kind' => 'loan',
            ]);

        $credit = $this->computedBills($period, $creditDues)
            ->map(fn (array $bill): array => [
                'id' => 'credit-'.$bill['id'],
                'name' => $bill['name'],
                'amount' => (float) $bill['plan'],
                'paid' => $bill['paid'],
                'kind' => 'credit',
            ]);

        return $loans->concat($credit)->values()->all();
    }

    /**
     * @param  list<array{id: string, name: string, amount: float, paid: bool, kind: string}>|null  $dues
     * @return array{bills: float, loan: float, living: float}
     */
    public function plannedStats(Period $period, ?array $dues = null): array
    {
        $dues = collect($dues ?? $this->dues($period));
        $credit = round((float) $dues->where('kind', 'credit')->where('paid', false)->sum('amount'), 2);
        $loan = round((float) $dues->where('kind', 'loan')->sum('amount'), 2);

        return [
            'bills' => round($this->cashBillsForPeriod($period) + $credit, 2),
            'loan' => $loan,
            'living' => $this->livingPlan->for($period->year, $period->month),
        ];
    }

    /**
     * 31st pay covers living plus Cursor. 15th pay covers remaining bills, credit, and loans.
     * Savings already logged on a paycheck come out of that paycheck's leftover.
     * The last calendar day belongs to next month's 31st pay, not this month's 15th.
     *
     * @return array{
     *     living: float,
     *     first: array{pay: float, bills: list<array{name: string, amount: float}>, bills_total: float, saved: float, leftover: float},
     *     second: array{pay: float, bills: list<array{name: string, amount: float}>, bills_total: float, loans: float, saved: float, leftover: float},
     *     saved: float,
     *     approx_savings: float
     * }
     */
    public function funding(Period $period, ?float $firstPay = null, ?float $secondPay = null): array
    {
        $month = Period::month($period->year, $period->month);
        $living = $this->livingPlan->for($month->year, $month->month);
        $cash = $this->cashBillRows($month);
        $credit = $this->computedBills($month)
            ->filter(fn (array $bill): bool => (float) $bill['plan'] > 0)
            ->map(fn (array $bill): array => [
                'name' => $bill['name'],
                'amount' => round((float) $bill['plan'], 2),
            ])
            ->values();
        $firstBills = $cash
            ->filter(fn (array $row): bool => $row['first_pay'])
            ->map(fn (array $row): array => ['name' => $row['name'], 'amount' => $row['amount']])
            ->values();
        $secondBills = $cash
            ->reject(fn (array $row): bool => $row['first_pay'])
            ->map(fn (array $row): array => ['name' => $row['name'], 'amount' => $row['amount']])
            ->concat($credit)
            ->values();
        $loans = round((float) $this->loans($month)
            ->filter(fn (array $loan): bool => $loan['due_this_period'])
            ->sum(fn (array $loan): float => $loan['paid_this_period']
                ? (float) $loan['spent_this_period']
                : (float) ($loan['installment_amount'] ?? 0)), 2);
        $firstTotal = round((float) $firstBills->sum('amount'), 2);
        $secondTotal = round((float) $secondBills->sum('amount'), 2);
        $firstPay = round((float) ($firstPay ?? 0), 2);
        $secondPay = round((float) ($secondPay ?? 0), 2);
        $monthStart = CarbonImmutable::create($month->year, $month->month, 1)->startOfDay();
        $firstOn = $monthStart->subDay();
        $secondOn = $monthStart->day(15);
        $nextFirstOn = $monthStart->endOfMonth()->startOfDay();
        $firstSaved = $this->paySavings($firstOn, $secondOn);
        $secondSaved = $this->paySavings($secondOn, $nextFirstOn);
        $firstLeft = round($firstPay - $living - $firstTotal - $firstSaved, 2);
        $secondLeft = round($secondPay - $secondTotal - $loans - $secondSaved, 2);

        return [
            'living' => $living,
            'first' => [
                'pay' => $firstPay,
                'bills' => $firstBills->all(),
                'bills_total' => $firstTotal,
                'saved' => $firstSaved,
                'leftover' => $firstLeft,
            ],
            'second' => [
                'pay' => $secondPay,
                'bills' => $secondBills->all(),
                'bills_total' => $secondTotal,
                'loans' => $loans,
                'saved' => $secondSaved,
                'leftover' => $secondLeft,
            ],
            'saved' => round($firstSaved + $secondSaved, 2),
            'approx_savings' => round($firstLeft + $secondLeft, 2),
        ];
    }

    /**
     * Dashboard tiles for a paycheck. 1–15 is the 31st pay (living + Cursor);
     * 16–end is the 15th pay (remaining bills + loans).
     *
     * @param  array{
     *     first: array{bills_total: float, saved: float, leftover: float},
     *     second: array{bills_total: float, loans: float, saved: float, leftover: float},
     *     saved: float,
     *     approx_savings: float
     * }  $funding
     * @return array{bills: float, loan: float, saved: float, leftover: float}
     */
    public function payTiles(array $funding, ?int $half): array
    {
        return match ($half) {
            1 => [
                'bills' => $funding['first']['bills_total'],
                'loan' => 0.0,
                'saved' => $funding['first']['saved'],
                'leftover' => $funding['first']['leftover'],
            ],
            2 => [
                'bills' => $funding['second']['bills_total'],
                'loan' => $funding['second']['loans'],
                'saved' => $funding['second']['saved'],
                'leftover' => $funding['second']['leftover'],
            ],
            default => [
                'bills' => round($funding['first']['bills_total'] + $funding['second']['bills_total'], 2),
                'loan' => $funding['second']['loans'],
                'saved' => $funding['saved'],
                'leftover' => $funding['approx_savings'],
            ],
        };
    }

    /**
     * Savings from $from inclusive through the day before $until.
     */
    private function paySavings(CarbonImmutable $from, CarbonImmutable $until): float
    {
        $to = $until->subDay();

        if ($to->lt($from)) {
            return 0.0;
        }

        return Money::pesos((int) SavingsEntry::query()
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->sum(fn (SavingsEntry $entry): int => $entry->towardCashLeftCents()));
    }

    /**
     * Paid cash bills use the amount actually paid. Unpaid bills stay on budget.
     */
    private function cashBillsForPeriod(Period $period): float
    {
        return round((float) $this->cashBillRows($period)
            ->filter(fn (array $row): bool => $this->dueWhen->matchesHalf(
                $this->dueWhen->for('bills', $row['id']),
                $period->half,
            ))
            ->sum('amount'), 2);
    }

    /**
     * @return Collection<int, array{id: int, name: string, slug: string, amount: float, first_pay: bool}>
     */
    private function cashBillRows(Period $period): Collection
    {
        $bills = Category::query()
            ->where('kind', 'bill')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->reject(fn (Category $bill): bool => $this->wallets->isCreditAccount($bill->slug, $bill->name))
            ->values();
        $spentByCategory = $bills->isEmpty()
            ? collect()
            : Transaction::query()
                ->whereIn('category_id', $bills->pluck('id'))
                ->where('type', 'bill')
                ->whereDate('occurred_on', '>=', $period->start->toDateString())
                ->whereDate('occurred_on', '<=', $period->end->toDateString())
                ->get()
                ->groupBy('category_id')
                ->map(fn (Collection $rows): float => round((float) $rows->sum(
                    fn (Transaction $row): float => $row->direction === 'in' ? -(float) $row->amount : (float) $row->amount,
                ), 2));

        return $bills->map(function (Category $bill) use ($spentByCategory): array {
            $plan = (float) $bill->monthly_plan;
            $spent = (float) ($spentByCategory->get($bill->id) ?? $spentByCategory->get((string) $bill->id) ?? 0);

            return [
                'id' => $bill->id,
                'name' => $bill->name,
                'slug' => $bill->slug,
                'amount' => round($plan > 0 && $spent > 0 ? $spent : $plan, 2),
                'first_pay' => $this->isFirstPayBill($bill),
            ];
        })->values();
    }

    private function isFirstPayBill(Category $bill): bool
    {
        return strtolower($bill->slug) === 'cursor' || strtolower($bill->name) === 'cursor';
    }

    /**
     * @param  array<string, mixed>  $credit
     * @param  Collection<int, Category>  $categories
     */
    private function categoryForCredit(array $credit, Collection $categories): ?Category
    {
        $slug = Str::lower((string) ($credit['slug'] ?? ''));

        if ($slug === '') {
            return null;
        }

        return $categories->first(function (Category $category) use ($slug): bool {
            $hay = Str::lower($category->slug.' '.Str::slug($category->name));

            return str_contains($hay, $slug);
        });
    }

    /**
     * @param  Collection<int, Transaction>  $sheetPays
     */
    private function lastPaidOn(Debt $debt, Collection $sheetPays): ?CarbonImmutable
    {
        $dates = $debt->payments
            ->map(fn (DebtPayment $payment): ?CarbonImmutable => $payment->occurred_on?->toImmutable()->startOfDay())
            ->concat($sheetPays->map(
                fn (Transaction $row): ?CarbonImmutable => $row->occurred_on?->toImmutable()->startOfDay(),
            ))
            ->filter();

        if ($dates->isEmpty()) {
            return null;
        }

        return $dates->sortBy(fn (CarbonImmutable $date): string => $date->toDateString())->last();
    }

    private function inRemainingWindow(Period $period, ?CarbonImmutable $lastOn, ?int $remaining): bool
    {
        if ($remaining === null || $remaining <= 0) {
            return false;
        }

        $periodMonth = $period->start->startOfMonth();
        $windowStart = $lastOn === null
            ? CarbonImmutable::today()->startOfMonth()->addMonth()
            : $lastOn->startOfMonth()->addMonth();
        $offset = ($periodMonth->year * 12 + $periodMonth->month)
            - ($windowStart->year * 12 + $windowStart->month);

        return $offset >= 0 && $offset < $remaining;
    }

    private function isSPayOrLazPay(array $credit): bool
    {
        $hay = Str::lower((string) ($credit['slug'] ?? '').' '.(string) ($credit['name'] ?? ''));

        return str_contains($hay, 'spay') || str_contains($hay, 'lazpay');
    }
}
