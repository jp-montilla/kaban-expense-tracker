<?php

namespace App\Http\Controllers;

use App\Http\Requests\BudgetRequest;
use App\Models\Bonus;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Salary;
use App\Models\Setting;
use App\Services\Ledger;
use App\Services\Wallets;
use App\Support\Cutoff;
use App\Support\Money;
use App\Support\Ownership;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function edit(Request $request, Ledger $ledger, Wallets $wallets): Response
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
        $billPlan = (float) $bills->sum(fn (Category $bill): float => (float) $bill->monthly_plan);
        $creditPlan = Money::pesos($wallets->unpaidDueCentsForMonth($year, $month));
        $loanPlan = (float) $loans->sum(fn (array $loan): float => (float) ($loan['is_monthly'] ? ($loan['installment_amount'] ?? 0) : 0));
        $living = (float) ($request->user()->living_plan ?? 0);

        if ($living <= 0) {
            $living = (float) (Setting::query()->where('key', Setting::LIVING_PLAN)->value('value') ?? 17000);
        }

        $budgetCycle = Setting::getValue(Setting::BUDGET_CYCLE, 'month') === 'cutoff' ? 'cutoff' : 'month';
        $scale = $budgetCycle === 'cutoff' ? 2 : 1;
        $billPlanView = round(($billPlan * $scale) + $creditPlan, 2);
        $loanPlanView = round($loanPlan * $scale, 2);
        $livingView = round($living * $scale, 2);
        $spending = $billPlanView + $loanPlanView + $livingView;

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
            'bill_plan' => $billPlanView,
            'loan_plan' => $loanPlanView,
            'spending_plan' => round($spending, 2),
            'left_if_followed' => round($monthPay - $spending, 2),
            'salaries' => $salaries->map(fn (Salary $salary): array => $salary->toListItem())->values(),
            'bonuses' => Bonus::query()->orderByDesc('received_on')->orderByDesc('id')->get()->map(fn (Bonus $bonus): array => $bonus->toListItem())->values(),
            'bills' => $bills->map(fn (Category $bill): array => [
                'id' => $bill->id,
                'name' => $bill->name,
                'slug' => $bill->slug,
                'monthly_plan' => (float) $bill->monthly_plan,
                'color' => $bill->color,
            ])->values(),
            'computed_bills' => $wallets->computedBillsForMonth($year, $month),
            'loans' => $loans,
        ]);
    }

    public function update(BudgetRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (($data['budget_cycle'] ?? null) === 'cutoff' || ($data['budget_cycle'] ?? null) === 'month') {
            Setting::putValue(Setting::BUDGET_CYCLE, $data['budget_cycle']);
        }

        if (Ownership::hasLivingPlan()) {
            $request->user()->update(['living_plan' => $data['living_plan']]);
        } else {
            Setting::putValue(Setting::LIVING_PLAN, (string) $data['living_plan']);
        }

        return redirect()
            ->route('budget.edit')
            ->with('success', 'Plan saved.');
    }
}
