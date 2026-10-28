<?php

namespace App\Http\Controllers;

use App\Http\Requests\DayLogRequest;
use App\Http\Requests\TransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\DueWhen;
use App\Services\Ledger;
use App\Services\MonthSheet;
use App\Services\Wallets;
use App\Support\CoverTag;
use App\Support\CreditTag;
use App\Support\DueTag;
use App\Support\Money;
use App\Support\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TransactionController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        return $this->page($request, $ledger, 'daily');
    }

    public function bills(Request $request, Ledger $ledger, Wallets $wallets, MonthSheet $sheet, DueWhen $dueWhen): Response
    {
        $period = Period::fromRequest($request);
        $types = ['bill', 'debt_payment'];
        $transactions = $ledger->listing($period->year, $period->month, $types, $period);
        $covering = Transaction::query()
            ->with('category')
            ->whereIn('type', $types)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Transaction $row): bool => CoverTag::inWindow(
                $row->notes,
                $row->occurred_on?->toImmutable(),
                $period->start,
                $period->end,
            ));
        $totals = $ledger->totals($transactions);
        $bills = Category::query()
            ->where('kind', 'bill')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->reject(fn (Category $bill): bool => $wallets->isCreditAccount($bill->slug, $bill->name))
            ->filter(fn (Category $bill): bool => $dueWhen->matchesHalf($dueWhen->for('bills', $bill->id), $period->half))
            ->map(function (Category $bill) use ($covering, $dueWhen, $period): array {
                $payments = $covering
                    ->filter(fn (Transaction $row): bool => $row->category_id === $bill->id && $row->type === 'bill');
                $spent = $payments->sum(fn (Transaction $row): float => $row->direction === 'in' ? -(float) $row->amount : (float) $row->amount);
                $latest = $payments->first();
                $item = $latest?->toListItem();
                $due = $dueWhen->decorate('bills', $bill->id);

                return [
                    'id' => $bill->id,
                    'name' => $bill->name,
                    'slug' => $bill->slug,
                    'color' => $bill->color,
                    'plan' => (float) $bill->monthly_plan,
                    'spent' => round((float) $spent, 2),
                    'paid' => round((float) $spent, 2) > 0,
                    'due_mode' => $due['mode'],
                    'due_day' => $due['day'],
                    'due_label' => $due['label'],
                    'covers_on' => $dueWhen->dateIn($period->year, $period->month, $due),
                    'payment' => $item === null ? null : [
                        'id' => $item['id'],
                        'occurred_on' => $item['occurred_on'],
                        'covers_on' => $item['covers_on'] ?? null,
                        'amount' => $item['amount'],
                        'payment_method' => $item['payment_method'],
                        'notes' => $item['notes'],
                        'via_credit' => $item['via_credit'],
                        'credit_months' => $item['credit_months'],
                        'credit_monthly' => $item['credit_monthly'],
                    ],
                ];
            })
            ->values();
        $creditDues = $sheet->creditDues($period);
        $planned = $sheet->plannedStats($period);
        $seen = [];
        $rows = $transactions
            ->concat($covering)
            ->reject(fn (Transaction $transaction): bool => CreditTag::chargeId($transaction->notes) !== null
                && DueTag::id($transaction->notes) === null)
            ->filter(function (Transaction $transaction) use (&$seen): bool {
                if (isset($seen[$transaction->id])) {
                    return false;
                }

                $seen[$transaction->id] = true;

                return true;
            })
            ->sortByDesc(fn (Transaction $transaction): string => $transaction->occurred_on->toDateString().'-'.$transaction->id)
            ->values()
            ->map(fn (Transaction $transaction): array => [
                ...$transaction->toListItem(),
                'source' => 'transaction',
            ]);

        return Inertia::render('Bills/Index', [
            'period' => $period->toArray(),
            'pay_on' => $period->payOn(),
            'bills' => $bills,
            'computed_bills' => $sheet->computedBills($period, $creditDues),
            'loans' => $sheet->loans($period)
                ->filter(fn (array $loan): bool => $dueWhen->matchesHalf(
                    ['mode' => $loan['due_mode'] ?? null, 'day' => $loan['due_day'] ?? null],
                    $period->half,
                ))
                ->values(),
            'credit_dues' => $creditDues,
            'advance_dues' => $sheet->advanceDues($period),
            'wallets' => $wallets->all(),
            'pay_sources' => $wallets->paySources(),
            'payments' => $rows,
            'totals' => [
                'bills' => $planned['bills'],
                'loan' => $planned['loan'],
                'actual' => Money::pesos($totals['actual']),
            ],
        ]);
    }

    public function storeDay(DayLogRequest $request): RedirectResponse
    {
        $this->writeDayItems($request->date('occurred_on')->toDateString(), $request->validated('items'), true);

        return back()->with('success', 'Saved.');
    }

    public function syncDay(DayLogRequest $request, string $day): RedirectResponse
    {
        abort_unless($request->date('occurred_on')->toDateString() === $day, 422);

        $this->writeDayItems($day, $request->validated('items'), false);

        return back()->with('success', 'Updated.');
    }

    public function destroyDay(string $day, Wallets $wallets): RedirectResponse
    {
        $rows = Transaction::query()
            ->whereDate('occurred_on', $day)
            ->whereIn('type', ['daily', 'refund'])
            ->get();

        foreach ($rows as $row) {
            $this->dropCredit($row, $wallets);
            $row->delete();
        }

        return back()->with('success', 'Removed.');
    }

    public function store(TransactionRequest $request): RedirectResponse
    {
        Transaction::query()->create($request->transactionAttributes());

        return back()->with('success', 'Saved.');
    }

    public function update(TransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $transaction->update($request->transactionAttributes());

        return back()->with('success', 'Updated.');
    }

    public function destroy(Transaction $transaction, Wallets $wallets): RedirectResponse
    {
        $dueId = DueTag::id($transaction->notes);

        if ($dueId !== null) {
            $wallets->unpayDue($dueId);
        }

        $this->dropCredit($transaction, $wallets);
        $transaction->delete();

        return back()->with('success', 'Removed.');
    }

    /**
     * @param  list<array{id?: int|null, description: string, amount: mixed, type: string, via_credit?: mixed, credit_method?: string|null, credit_months?: int|null, credit_monthly?: mixed}>  $items
     */
    private function writeDayItems(string $day, array $items, bool $append): void
    {
        $categoryId = Category::query()->where('slug', 'daily')->value('id');
        $wallets = app(Wallets::class);

        abort_if($categoryId === null, 422, 'Living category is missing.');

        DB::transaction(function () use ($day, $items, $append, $categoryId, $wallets): void {
            $existing = Transaction::query()
                ->whereDate('occurred_on', $day)
                ->whereIn('type', ['daily', 'refund'])
                ->get()
                ->keyBy('id');
            $keptIds = [];

            foreach ($items as $item) {
                $type = $item['type'];
                $attributes = [
                    'occurred_on' => $day,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'type' => $type,
                    'direction' => $type === 'refund' ? 'in' : 'out',
                    'category_id' => $categoryId,
                    'notes' => null,
                ];

                if ($append || empty($item['id'])) {
                    $row = Transaction::query()->create($attributes);
                    $this->syncCredit($row, $item, $wallets, $day);

                    continue;
                }

                $row = $existing->get((int) $item['id']);

                abort_if($row === null, 404);

                $row->update($attributes);
                $this->syncCredit($row, $item, $wallets, $day);
                $keptIds[] = $row->id;
            }

            if ($append) {
                return;
            }

            $existing
                ->reject(fn (Transaction $row): bool => in_array($row->id, $keptIds, true))
                ->each(function (Transaction $row) use ($wallets): void {
                    $this->dropCredit($row, $wallets);
                    $row->delete();
                });
        });
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function syncCredit(Transaction $row, array $item, Wallets $wallets, string $day): void
    {
        $this->dropCredit($row, $wallets);

        $viaCredit = filter_var($item['via_credit'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $viaCredit || $row->type === 'refund') {
            $row->update(['notes' => null]);

            return;
        }

        $credit = $wallets->find((string) $item['credit_method']);
        abort_unless($credit !== null && $credit['group'] === 'credit', 422, 'Unknown credit option.');

        $months = (int) $item['credit_months'];
        $monthly = (float) ($item['credit_monthly'] ?? 0);

        if ($months === 1 && $monthly < 0.01) {
            $monthly = (float) $item['amount'];
        }
        $charge = $wallets->addCharge($credit['id'], [
            'description' => $row->description,
            'months' => $months,
            'monthly' => $monthly,
            'first_due_on' => $wallets->firstDueOn($credit, $day),
        ]);

        $row->update([
            'notes' => CreditTag::wrap($credit['slug'], $months, $monthly, $charge['id'], null),
        ]);
    }

    private function dropCredit(Transaction $row, Wallets $wallets): void
    {
        $chargeId = CreditTag::chargeId($row->notes);

        if ($chargeId === null) {
            return;
        }

        try {
            $wallets->removeChargeById($chargeId);
        } catch (InvalidArgumentException) {
            // Paid installments stay on bills.
        }
    }

    private function page(Request $request, Ledger $ledger, string $mode): Response
    {
        $period = $mode === 'daily'
            ? $this->logPeriod($request)
            : Period::fromRequest($request);
        $year = $period->year;
        $month = $period->month;
        $types = $mode === 'bills'
            ? ['bill', 'debt_payment']
            : ['daily', 'refund'];
        $kinds = $mode === 'bills'
            ? ['bill', 'debt']
            : ['variable'];

        $transactions = $ledger->listing($year, $month, $types, $period);
        $totals = $ledger->totals($transactions);
        $rows = $transactions->map(function (Transaction $transaction): array {
            return [
                ...$transaction->toListItem(),
                'source' => 'transaction',
            ];
        });

        return Inertia::render('Transactions/Index', [
            'mode' => $mode,
            'period' => $period->toArray(),
            'transactions' => $rows->values(),
            'totals' => [
                'bills' => Money::pesos($totals['bills']),
                'loan' => Money::pesos($totals['loan']),
                'daily' => Money::pesos($totals['daily']),
                'actual' => Money::pesos($totals['actual']),
            ],
            'categories' => Category::query()
                ->where('is_active', true)
                ->whereIn('kind', $kinds)
                ->when($mode === 'daily', fn ($query) => $query->where('slug', 'daily'))
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'kind', 'color'])
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'kind' => $category->kind,
                    'color' => $category->color,
                ])
                ->all(),
        ]);
    }

    private function logPeriod(Request $request): Period
    {
        $today = now();
        $year = max(2000, min(2100, $request->integer('year', $today->year)));
        $month = max(1, min(12, $request->integer('month', $today->month)));

        return Period::month($year, $month);
    }
}
