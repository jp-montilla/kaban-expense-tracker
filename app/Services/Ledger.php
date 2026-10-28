<?php

namespace App\Services;

use App\Models\Bonus;
use App\Models\Category;
use App\Models\Salary;
use App\Models\SavingsEntry;
use App\Models\Setting;
use App\Models\Transaction;
use App\Support\CreditTag;
use App\Support\Cutoff;
use App\Support\DueTag;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Ledger
{
    /**
     * @return array<string, mixed>
     */
    public function overview(int $year, int $month, ?int $half = null, ?Period $period = null): array
    {
        $period ??= Period::month($year, $month, $half);
        $year = $period->year;
        $month = $period->month;
        $half = $period->half;
        $cursor = $period->end->startOfMonth();
        $rangeStart = $cursor->subMonths(11)->startOfMonth();
        $monthEnd = CarbonImmutable::create($year, $month, 1)->endOfMonth();
        $loadEnd = $period->end->greaterThan($monthEnd) ? $period->end : $monthEnd;
        $categories = Category::query()->where('is_active', true)->orderBy('sort_order')->get();
        $transactions = $this->transactionsBetween($rangeStart, $loadEnd);
        $savings = $this->savingsBetween($rangeStart, $loadEnd);
        $salaries = Salary::query()->orderBy('effective_on')->orderBy('id')->get();
        $bonuses = Bonus::query()
            ->whereBetween('received_on', [$rangeStart->toDateString(), $loadEnd->toDateString()])
            ->orderBy('received_on')
            ->get();
        $latestSalary = $salaries->last();
        $scale = $this->planScale($period);
        $plans = $this->plans($categories, $scale, $year, $month);

        $trend = [];

        for ($offset = 0; $offset < 12; $offset++) {
            $point = $rangeStart->addMonths($offset);
            $monthTransactions = $this->inMonth($transactions, $point->year, $point->month);
            $totals = $this->totals($monthTransactions);
            $saved = $this->savingsTowardCashLeft($this->savingsInMonth($savings, $point->year, $point->month));
            [$monthStart, $monthEnd] = Cutoff::range($point->year, $point->month);
            $income = $this->resolveIncome($salaries, $bonuses, $point, $monthStart, $monthEnd, null);

            $trend[] = [
                'year' => $point->year,
                'month' => $point->month,
                'label' => $point->format('M'),
                'full_label' => $point->format('F Y'),
                'income' => $this->pesosOrNull($income['cents']),
                'actual' => Money::pesos($totals['actual']),
                'cash_left' => $income['cents'] === null ? null : Money::pesos($income['cents'] - $totals['actual'] - $saved),
            ];
        }

        $start = $period->start;
        $end = $period->end;
        $viewTransactions = $this->between($transactions, $start, $end);
        $monthTransactions = $this->inMonth($transactions, $year, $month);
        $viewTotals = $this->totals($viewTransactions);
        $monthTotals = $this->totals($monthTransactions);
        $viewSaved = $this->savingsTowardCashLeft($this->savingsInRange($savings, $start, $end));
        $income = $this->resolvePeriodIncome($salaries, $bonuses, $period);
        $variableSpent = $this->variableSpent($viewTransactions);
        $variableLeft = $plans['variable'] - $variableSpent;
        $daysLeft = $period->daysLeft();

        return [
            'period' => [
                ...$period->toArray(),
                'month_label' => Cutoff::label($year, $month),
            ],
            'figures' => [
                'income' => $this->pesosOrNull($income['cents']),
                'income_source' => $income['source'],
                'income_since' => $income['since'],
                'cutoff_pay' => $this->pesosOrNull($income['cutoff']),
                'first_cutoff' => $this->pesosOrNull($income['first_cutoff']),
                'second_cutoff' => $this->pesosOrNull($income['second_cutoff']),
                'pay_mode' => $income['pay_mode'],
                'funding_on' => $income['funding_on'],
                'savings_pay_on' => $income['savings_pay_on'],
                'savings_pay' => $this->pesosOrNull($income['savings_pay']),
                'bonus' => Money::pesos($income['bonus']),
                'latest_income' => $latestSalary ? (float) $latestSalary->amount : null,
                'latest_since' => $latestSalary?->effective_on->toDateString(),
                'view_bills' => Money::pesos($viewTotals['bills']),
                'view_loan' => Money::pesos($viewTotals['loan']),
                'view_daily' => Money::pesos($viewTotals['daily']),
                'view_actual' => Money::pesos($viewTotals['actual']),
                'view_savings' => Money::pesos($viewSaved),
                'month_spent' => Money::pesos($monthTotals['spent']),
                'month_actual' => Money::pesos($monthTotals['actual']),
                'cash_left' => $income['cents'] === null ? null : Money::pesos($income['cents'] - $viewTotals['actual'] - $viewSaved),
                'spending_plan' => Money::pesos($plans['spending']),
                'plan_left' => Money::pesos($plans['spending'] - $monthTotals['spent']),
                'variable_plan' => Money::pesos($plans['variable']),
                'variable_spent' => Money::pesos($variableSpent),
                'variable_left' => Money::pesos($variableLeft),
                'daily_budget' => $daysLeft > 0 ? Money::pesos((int) round($variableLeft / $daysLeft)) : null,
                'savings_balance' => Money::pesos(SavingsEntry::balanceCents()),
            ],
            'breakdown' => $this->categoryBreakdown($categories, $viewTransactions),
            'top_spends' => [
                'month' => $this->rankCategories($monthTransactions),
                'year' => $this->rankCategories($this->spendTransactions()->whereYear('occurred_on', $year)->get()),
                'all' => $this->rankCategories($this->spendTransactions()->get()),
            ],
            'top_living' => [
                'month' => $this->rankLiving($monthTransactions),
                'year' => $this->rankLivingQuery($year),
                'all' => $this->rankLivingQuery(null),
            ],
            'trend' => $trend,
            'recent' => ($period->span === 'month' ? $monthTransactions : $viewTransactions)
                ->take(6)
                ->map(fn (Transaction $transaction) => $transaction->toListItem())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function year(int $year): array
    {
        $transactions = Transaction::query()
            ->with('category')
            ->whereYear('occurred_on', $year)
            ->get();
        $savings = SavingsEntry::query()
            ->whereYear('occurred_on', $year)
            ->get();
        $salaries = Salary::query()->orderBy('effective_on')->orderBy('id')->get();
        $bonuses = Bonus::query()
            ->whereYear('received_on', $year)
            ->orderBy('received_on')
            ->get();
        $months = [];
        $yearActual = 0;
        $yearSaved = 0;
        $yearIncome = 0;
        $yearIncomeKnown = false;

        for ($month = 12; $month >= 1; $month--) {
            $halves = [];

            foreach ([1, 2] as $half) {
                [$start, $end] = Cutoff::range($year, $month, $half);
                $totals = $this->totals($this->between($transactions, $start, $end));
                $halves[] = [
                    'half' => $half,
                    'label' => $half === 1 ? '1–15' : '16–end',
                    'bills' => Money::pesos($totals['bills']),
                    'loan' => Money::pesos($totals['loan']),
                    'daily' => Money::pesos($totals['daily']),
                    'actual' => Money::pesos($totals['actual']),
                ];
            }

            $monthTotals = $this->totals($this->inMonth($transactions, $year, $month));
            $monthSaved = $this->savingsTowardCashLeft($this->savingsInMonth($savings, $year, $month));
            [$monthStart, $monthEnd] = Cutoff::range($year, $month);
            $income = $this->resolveIncome($salaries, $bonuses, CarbonImmutable::create($year, $month, 1), $monthStart, $monthEnd, null);
            $yearActual += $monthTotals['actual'];
            $yearSaved += $monthSaved;

            $isCurrentOrPast = CarbonImmutable::create($year, $month, 1)->lessThanOrEqualTo(CarbonImmutable::today()->startOfMonth());

            if ($income['cents'] !== null && ($monthTotals['actual'] !== 0 || $isCurrentOrPast)) {
                $yearIncome += $income['cents'];
                $yearIncomeKnown = true;
            }

            $months[] = [
                'month' => $month,
                'label' => CarbonImmutable::create($year, $month, 1)->format('F'),
                'halves' => $halves,
                'bills' => Money::pesos($monthTotals['bills']),
                'loan' => Money::pesos($monthTotals['loan']),
                'daily' => Money::pesos($monthTotals['daily']),
                'actual' => Money::pesos($monthTotals['actual']),
                'income' => $this->pesosOrNull($income['cents']),
                'income_source' => $income['source'],
                'cash_left' => $income['cents'] === null ? null : Money::pesos($income['cents'] - $monthTotals['actual'] - $monthSaved),
                'has_activity' => $monthTotals['actual'] !== 0,
                'income_since' => $income['since'],
            ];
        }

        return [
            'year' => $year,
            'months' => $months,
            'actual' => Money::pesos($yearActual),
            'income' => $yearIncomeKnown ? Money::pesos($yearIncome) : null,
            'cash_left' => $yearIncomeKnown ? Money::pesos($yearIncome - $yearActual - $yearSaved) : null,
        ];
    }

    /**
     * @param  array<int, string>  $types
     * @return Collection<int, Transaction>
     */
    public function listing(int $year, int $month, array $types, ?Period $period = null): Collection
    {
        $period ??= Period::month($year, $month);
        $start = $period->start;
        $end = $period->end;

        return Transaction::query()
            ->with('category')
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->whereIn('type', $types)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array{bills: int, loan: int, daily: int, spent: int, actual: int}
     */
    public function totals(Collection $transactions): array
    {
        $bills = 0;
        $loan = 0;
        $daily = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->type === 'savings') {
                continue;
            }

            $signed = $this->signed($transaction);

            if ($transaction->type === 'daily' || $transaction->type === 'refund') {
                $daily += $signed;
            } elseif ($transaction->type === 'debt_payment' || $transaction->category?->kind === 'debt') {
                $loan += $signed;
            } else {
                $bills += $signed;
            }
        }

        $spent = $bills + $loan + $daily;

        return [
            'bills' => $bills,
            'loan' => $loan,
            'daily' => $daily,
            'spent' => $spent,
            'actual' => $spent,
        ];
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array{spending: int, variable: int, committed: int}
     */
    private function plans(Collection $categories, int $scale = 1, int $year = 2026, int $month = 1): array
    {
        $living = Money::cents(auth()->user()?->living_plan ?? Setting::getValue(Setting::LIVING_PLAN, '17000')) * $scale;
        $committed = 0;
        $wallets = app(Wallets::class);

        foreach ($categories as $category) {
            if (! in_array($category->kind, ['bill', 'debt'], true) || ! $category->is_active) {
                continue;
            }

            if ($category->kind === 'bill' && $wallets->isCreditAccount($category->slug, $category->name)) {
                continue;
            }

            $committed += Money::cents($category->monthly_plan) * $scale;
        }

        $committed += $wallets->unpaidDueCentsForMonth($year, $month);

        return [
            'spending' => $committed + $living,
            'variable' => $living,
            'committed' => $committed,
        ];
    }

    private function planScale(Period $period): int
    {
        $cycle = Setting::getValue(Setting::BUDGET_CYCLE, 'month');

        if ($cycle === 'cutoff') {
            return $period->half ? $period->monthCount() : $period->monthCount() * 2;
        }

        return $period->monthCount();
    }

    /**
     * @param  Collection<int, Salary>  $salaries
     * @param  Collection<int, Bonus>  $bonuses
     * @return array<string, mixed>
     */
    private function resolvePeriodIncome(Collection $salaries, Collection $bonuses, Period $period): array
    {
        if ($period->monthCount() === 1) {
            $cursor = CarbonImmutable::create($period->year, $period->month, 1);

            return $this->resolveIncome($salaries, $bonuses, $cursor, $period->start, $period->end, $period->half);
        }

        $cents = 0;
        $known = false;
        $merged = null;

        foreach ($period->months() as $point) {
            $cursor = CarbonImmutable::create($point['year'], $point['month'], 1);
            [$start, $end] = Cutoff::range($point['year'], $point['month']);
            $piece = $this->resolveIncome($salaries, $bonuses, $cursor, $start, $end, null);

            if ($piece['cents'] !== null) {
                $cents += $piece['cents'];
                $known = true;
            }

            $merged = $piece;
        }

        $merged ??= $this->resolveIncome($salaries, $bonuses, $period->start, $period->start, $period->end, null);
        $merged['cents'] = $known ? $cents : null;

        return $merged;
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     */
    private function variableSpent(Collection $transactions): int
    {
        return (int) $transactions->sum(function (Transaction $transaction): int {
            if ($transaction->type === 'savings') {
                return 0;
            }

            $kind = $transaction->category?->kind;

            if (in_array($kind, ['bill', 'debt', 'savings', 'vehicle'], true) || $transaction->category?->slug === 'z400') {
                return 0;
            }

            return $this->signed($transaction);
        });
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @param  Collection<int, Transaction>  $transactions
     * @return list<array<string, mixed>>
     */
    private function categoryBreakdown(Collection $categories, Collection $transactions): array
    {
        $spent = [];

        foreach ($transactions as $transaction) {
            if ($transaction->category_id === null) {
                continue;
            }

            $spent[$transaction->category_id] = ($spent[$transaction->category_id] ?? 0) + $this->signed($transaction);
        }

        return $categories
            ->map(function (Category $category) use ($spent): array {
                $cents = $spent[$category->id] ?? 0;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'kind' => $category->kind,
                    'color' => $category->color,
                    'plan' => (float) $category->monthly_plan,
                    'spent' => Money::pesos($cents),
                ];
            })
            ->filter(fn (array $category): bool => $category['plan'] > 0 || abs($category['spent']) >= 0.01)
            ->sortBy([
                ['spent', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return list<array<string, mixed>>
     */
    public function rankCategories(Collection $transactions, int $limit = 8): array
    {
        $spent = [];
        $meta = [];

        foreach ($transactions as $transaction) {
            if ($transaction->type === 'savings' || $transaction->category_id === null) {
                continue;
            }

            $cents = $this->signed($transaction);

            if ($cents === 0) {
                continue;
            }

            $spent[$transaction->category_id] = ($spent[$transaction->category_id] ?? 0) + $cents;
            $meta[$transaction->category_id] ??= [
                'id' => $transaction->category_id,
                'name' => $transaction->category?->name ?? 'Uncategorized',
                'color' => $transaction->category?->color ?? '#241f1b',
            ];
        }

        $total = array_sum(array_map(fn (int $cents): int => max(0, $cents), $spent));
        $ranked = collect($spent)
            ->map(function (int $cents, int $id) use ($meta, $total): array {
                return [
                    ...$meta[$id],
                    'spent' => Money::pesos($cents),
                    'share' => $total > 0 && $cents > 0 ? round(($cents / $total) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('spent')
            ->values()
            ->take($limit)
            ->values();

        return $ranked
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->all();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return list<array{rank: int, id: int, description: string, amount: float, occurred_on: string}>
     */
    public function rankLiving(Collection $transactions, int $limit = 10): array
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => $transaction->type === 'daily'
                && $transaction->direction === 'out'
                && (float) $transaction->amount >= 0.01)
            ->sortByDesc(fn (Transaction $transaction): float => (float) $transaction->amount)
            ->take($limit)
            ->values()
            ->map(fn (Transaction $transaction, int $index): array => [
                'rank' => $index + 1,
                'id' => $transaction->id,
                'description' => $transaction->description,
                'amount' => (float) $transaction->amount,
                'occurred_on' => $transaction->occurred_on->toDateString(),
            ])
            ->all();
    }

    /**
     * @return list<array{rank: int, id: int, description: string, amount: float, occurred_on: string}>
     */
    private function rankLivingQuery(?int $year, int $limit = 10): array
    {
        $query = Transaction::query()
            ->where('type', 'daily')
            ->where('direction', 'out')
            ->orderByDesc('amount')
            ->orderByDesc('occurred_on')
            ->limit($limit);

        if ($year !== null) {
            $query->whereYear('occurred_on', $year);
        }

        return $this->rankLiving($query->get(), $limit);
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function transactionsBetween(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Transaction::query()
            ->with('category')
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return Collection<int, SavingsEntry>
     */
    private function savingsBetween(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return SavingsEntry::query()
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->get();
    }

    /**
     * @return Builder<Transaction>
     */
    private function spendTransactions(): Builder
    {
        return Transaction::query()
            ->with('category')
            ->where('type', '!=', 'savings');
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, Transaction>
     */
    private function inMonth(Collection $transactions, int $year, int $month): Collection
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => (int) $transaction->occurred_on->year === $year && (int) $transaction->occurred_on->month === $month)
            ->values();
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return Collection<int, Transaction>
     */
    private function between(Collection $transactions, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $from = $start->toDateString();
        $to = $end->toDateString();

        return $transactions
            ->filter(function (Transaction $transaction) use ($from, $to): bool {
                $date = $transaction->occurred_on->toDateString();

                return $date >= $from && $date <= $to;
            })
            ->values();
    }

    /**
     * @param  Collection<int, SavingsEntry>  $entries
     * @return Collection<int, SavingsEntry>
     */
    private function savingsInMonth(Collection $entries, int $year, int $month): Collection
    {
        return $entries
            ->filter(fn (SavingsEntry $entry): bool => (int) $entry->occurred_on->year === $year && (int) $entry->occurred_on->month === $month)
            ->values();
    }

    /**
     * @param  Collection<int, SavingsEntry>  $entries
     * @return Collection<int, SavingsEntry>
     */
    private function savingsInRange(Collection $entries, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $from = $start->toDateString();
        $to = $end->toDateString();

        return $entries
            ->filter(function (SavingsEntry $entry) use ($from, $to): bool {
                $date = $entry->occurred_on->toDateString();

                return $date >= $from && $date <= $to;
            })
            ->values();
    }

    /**
     * @param  Collection<int, SavingsEntry>  $entries
     */
    private function savingsTowardCashLeft(Collection $entries): int
    {
        return (int) $entries->sum(fn (SavingsEntry $entry): int => $entry->towardCashLeftCents());
    }

    /**
     * Each month is last month-end pay (rate on the 1st) plus this month's
     * 15 pay (rate on the 16th). October 2026 is ₱30,000 then ₱55,000.
     * November 2026 is ₱55,000 then ₱55,000.
     *
     * @param  Collection<int, Salary>  $salaries
     * @param  Collection<int, Bonus>  $bonuses
     * @return array{
     *     cents: int|null,
     *     cutoff: int|null,
     *     first_cutoff: int|null,
     *     second_cutoff: int|null,
     *     pay_mode: string,
     *     funding_on: string|null,
     *     savings_pay_on: string|null,
     *     savings_pay: int|null,
     *     bonus: int,
     *     source: string,
     *     since: string|null
     * }
     */
    public function resolveIncome(
        Collection $salaries,
        Collection $bonuses,
        CarbonImmutable $monthStart,
        CarbonImmutable $rangeStart,
        CarbonImmutable $rangeEnd,
        ?int $half,
    ): array {
        $bonusCents = (int) $bonuses
            ->filter(function (Bonus $bonus) use ($rangeStart, $rangeEnd): bool {
                $date = $bonus->received_on->toDateString();

                return $date >= $rangeStart->toDateString() && $date <= $rangeEnd->toDateString();
            })
            ->sum(fn (Bonus $bonus): int => Money::cents($bonus->amount));

        return $this->cutoffIncome($salaries, $monthStart, $half, $bonusCents);
    }

    /**
     * @param  Collection<int, Salary>  $salaries
     * @return array{
     *     cents: int|null,
     *     cutoff: int|null,
     *     first_cutoff: int|null,
     *     second_cutoff: int|null,
     *     pay_mode: string,
     *     funding_on: string|null,
     *     savings_pay_on: string|null,
     *     savings_pay: int|null,
     *     bonus: int,
     *     source: string,
     *     since: string|null
     * }
     */
    private function cutoffIncome(Collection $salaries, CarbonImmutable $monthStart, ?int $half, int $bonusCents): array
    {
        $first = $this->salaryOn($salaries, $monthStart);
        $second = $this->salaryOn($salaries, $monthStart->day(16));
        $firstCents = $first ? Money::cents($first->amount) : null;
        $secondCents = $second ? Money::cents($second->amount) : null;
        $chosen = $half === 2 ? $second : $first;
        $cutoffCents = $half === 2 ? $secondCents : $firstCents;
        $payCents = $half === null
            ? (($firstCents ?? 0) + ($secondCents ?? 0) ?: null)
            : $cutoffCents;
        $payMode = 'cutoff';

        if ($first && $first->payCycle() === 'month') {
            $payMode = 'month';
            $monthCents = $firstCents;
            $firstCents = $monthCents;
            $secondCents = 0;
            $cutoffCents = $half === null ? $monthCents : (int) round(($monthCents ?? 0) / 2);
            $payCents = $cutoffCents;
            $chosen = $first;
        }

        if ($payCents === 0) {
            $payCents = null;
        }

        if ($payCents === null && $bonusCents === 0) {
            return [
                'cents' => null,
                'cutoff' => null,
                'first_cutoff' => $firstCents,
                'second_cutoff' => $secondCents,
                'pay_mode' => $payMode,
                'funding_on' => null,
                'savings_pay_on' => null,
                'savings_pay' => null,
                'bonus' => 0,
                'source' => 'missing',
                'since' => null,
            ];
        }

        return [
            'cents' => ($payCents ?? 0) + $bonusCents,
            'cutoff' => $cutoffCents,
            'first_cutoff' => $firstCents,
            'second_cutoff' => $secondCents,
            'pay_mode' => $payMode,
            'funding_on' => null,
            'savings_pay_on' => null,
            'savings_pay' => null,
            'bonus' => $bonusCents,
            'source' => $chosen ? 'history' : 'bonus',
            'since' => $chosen?->effective_on->toDateString(),
        ];
    }

    /**
     * @param  Collection<int, Salary>  $salaries
     */
    private function salaryOn(Collection $salaries, CarbonImmutable $date): ?Salary
    {
        return $salaries
            ->filter(fn (Salary $salary): bool => $salary->effective_on->toDateString() <= $date->toDateString())
            ->last();
    }

    private function signed(Transaction $transaction): int
    {
        if (CreditTag::chargeId($transaction->notes) !== null && DueTag::id($transaction->notes) === null) {
            return 0;
        }

        $cents = Money::cents($transaction->amount);

        return $transaction->direction === 'in' ? -$cents : $cents;
    }

    private function pesosOrNull(?int $cents): ?float
    {
        return $cents === null ? null : Money::pesos($cents);
    }
}
