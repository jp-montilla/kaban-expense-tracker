<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetRequest;
use App\Models\Bonus;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Salary;
use App\Models\Setting;
use App\Services\DueWhen;
use App\Services\Ledger;
use App\Services\LivingPlan;
use App\Services\MonthSheet;
use App\Services\Wallets;
use App\Support\Cutoff;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function edit(Ledger $ledger, Wallets $wallets, DueWhen $dueWhen, LivingPlan $livingPlan, MonthSheet $sheet): Response
    {
        $monthStart = CarbonImmutable::today()->startOfMonth();
        $year = $monthStart->year;
        $month = $monthStart->month;
        $previousPayOn = $monthStart->subDay();
        $currentPayOn = $monthStart->day(15);
        $salaries = Salary::query()->orderByDesc('effective_on')->orderByDesc('id')->get();
        $latest = $salaries->first();
        [$rangeStart, $rangeEnd] = Cutoff::range($year, $month);
        $income = $ledger->resolveIncome(
            Salary::query()->orderBy('effective_on')->orderBy('id')->get(),
            Bonus::query()->whereBetween('received_on', [$rangeStart->toDateString(), $rangeEnd->toDateString()])->get(),
            $monthStart,
            $rangeStart,
            $rangeEnd,
            null,
        );
        $firstPay = $income['first_cutoff'] !== null ? Money::pesos($income['first_cutoff']) : 0.0;
        $secondPay = $income['second_cutoff'] !== null ? Money::pesos($income['second_cutoff']) : 0.0;
        $cutoffPay = $income['cutoff'] !== null ? Money::pesos($income['cutoff']) : 0.0;
        $monthPay = $firstPay + $secondPay;
        $bills = Category::query()
            ->where('kind', 'bill')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->reject(fn (Category $bill): bool => $wallets->isCreditAccount($bill->slug, $bill->name))
            ->values();
        $loans = Debt::query()
            ->with('payments')
            ->orderBy('status')
            ->orderBy('name')
            ->get()
            ->map(fn (Debt $debt): array => $debt->toSummaryArray())
            ->values();
        $living = $livingPlan->for($year, $month);
        $budgetCycle = Setting::getValue(Setting::BUDGET_CYCLE, 'month') === 'cutoff' ? 'cutoff' : 'month';
        $funding = $sheet->funding(Period::month($year, $month), $firstPay, $secondPay);
        $billPlanView = round($funding['first']['bills_total'] + $funding['second']['bills_total'], 2);
        $loanPlanView = $funding['second']['loans'];
        $livingView = $funding['living'];
        $spending = round($billPlanView + $loanPlanView + $livingView, 2);
        $livingFrom = collect($livingPlan->all())
            ->reverse()
            ->first(fn (array $row): bool => $row['effective_on'] <= $monthStart->toDateString());

        return Inertia::render('Budget/Edit', [
            'budget_month' => $monthStart->format('F Y'),
            'previous_pay_label' => $previousPayOn->format('F j'),
            'current_pay_label' => $currentPayOn->format('F j'),
            'budget_cycle' => $budgetCycle,
            'wallets' => $wallets->all(),
            'cutoff_pay' => $cutoffPay,
            'first_cutoff' => $firstPay,
            'second_cutoff' => $secondPay,
            'month_pay' => $monthPay,
            'pay_mode' => $income['pay_mode'],
            'funding_on' => $income['funding_on'],
            'savings_pay_on' => $income['savings_pay_on'],
            'savings_pay' => $income['savings_pay'] !== null ? Money::pesos($income['savings_pay']) : null,
            'income_since' => $income['since'],
            'latest_income' => $latest ? (float) $latest->amount : null,
            'latest_since' => $latest?->effective_on->toDateString(),
            'living_plan' => $living,
            'living_from' => $livingFrom
                ? CarbonImmutable::parse($livingFrom['effective_on'])->format('F Y')
                : $monthStart->format('F Y'),
            'funding' => $funding,
            'bill_plan' => $billPlanView,
            'loan_plan' => $loanPlanView,
            'spending_plan' => $spending,
            'left_if_followed' => $funding['approx_savings'],
            'salaries' => $salaries->map(fn (Salary $salary): array => $salary->toListItem())->values(),
            'bonuses' => Bonus::query()->orderByDesc('received_on')->orderByDesc('id')->get()->map(fn (Bonus $bonus): array => $bonus->toListItem())->values(),
            'bills' => $bills->map(function (Category $bill) use ($dueWhen): array {
                $due = $dueWhen->decorate('bills', $bill->id);

                return [
                    'id' => $bill->id,
                    'name' => $bill->name,
                    'slug' => $bill->slug,
                    'monthly_plan' => (float) $bill->monthly_plan,
                    'color' => $bill->color,
                    'due_mode' => $due['mode'],
                    'due_day' => $due['day'],
                    'due_label' => $due['label'],
                ];
            })->values(),
            'computed_bills' => $wallets->computedBillsForMonth($year, $month),
            'loans' => $loans->map(function (array $loan) use ($dueWhen): array {
                $due = $dueWhen->decorate('loans', $loan['id']);

                return [
                    ...$loan,
                    'due_mode' => $due['mode'],
                    'due_day' => $due['day'],
                    'due_label' => $due['label'],
                ];
            })->values(),
        ]);
    }

    public function update(BudgetRequest $request, LivingPlan $livingPlan): RedirectResponse
    {
        $data = $request->validated();

        if (($data['budget_cycle'] ?? null) === 'cutoff' || ($data['budget_cycle'] ?? null) === 'month') {
            Setting::putValue(Setting::BUDGET_CYCLE, $data['budget_cycle']);
        }

        $livingPlan->put((float) $data['living_plan'], CarbonImmutable::today()->startOfMonth());

        return redirect()
            ->route('budget.edit')
            ->with('success', 'Plan saved.');
    }
}
