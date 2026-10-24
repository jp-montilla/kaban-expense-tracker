<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavingsEntryRequest;
use App\Models\SavingsEntry;
use App\Services\Ledger;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavingsController extends Controller
{
    public function index(Request $request, Ledger $ledger): Response
    {
        if (! $request->filled('span')) {
            $request->merge(['span' => 'all']);
        }

        $period = Period::fromRequest($request);
        $today = CarbonImmutable::today();
        $budgetPeriod = $period->span === 'all'
            ? Period::month($today->year, $today->month)
            : $period;
        $overview = $ledger->overview($budgetPeriod->year, $budgetPeriod->month, $budgetPeriod->half, $budgetPeriod);

        $entries = SavingsEntry::query()
            ->when(
                $period->span !== 'all',
                fn ($query) => $query->whereBetween('occurred_on', [$period->start->toDateString(), $period->end->toDateString()]),
            )
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        $periodSaved = $period->span === 'all'
            ? Money::pesos((int) $entries->sum(fn (SavingsEntry $entry): int => $entry->towardCashLeftCents()))
            : $overview['figures']['view_savings'];

        return Inertia::render('Savings/Index', [
            'period' => $period->toArray(),
            'entries' => $entries
                ->map(fn (SavingsEntry $entry): array => [
                    ...$entry->toListItem(),
                    'opening' => $entry->isOpening(),
                    'toward_budget' => Money::pesos($entry->towardCashLeftCents()),
                ])
                ->values(),
            'month_saved' => $periodSaved,
            'cash_left' => $overview['figures']['cash_left'],
            'balance' => $overview['figures']['savings_balance'],
        ]);
    }

    public function store(SavingsEntryRequest $request): RedirectResponse
    {
        SavingsEntry::query()->create($request->validated());

        return back()->with('success', 'Savings updated. Budget left for the month moved with it.');
    }

    public function update(SavingsEntryRequest $request, SavingsEntry $entry): RedirectResponse
    {
        $entry->update($request->validated());

        return back()->with('success', 'Savings updated.');
    }

    public function destroy(SavingsEntry $entry): RedirectResponse
    {
        $entry->delete();

        return back()->with('success', 'Savings entry removed.');
    }
}
