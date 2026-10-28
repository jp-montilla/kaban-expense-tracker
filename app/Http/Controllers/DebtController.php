<?php

namespace App\Http\Controllers;

use App\Http\Requests\DebtPaymentRequest;
use App\Http\Requests\DebtRequest;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Transaction;
use App\Services\DueWhen;
use App\Services\Wallets;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DebtController extends Controller
{
    public function index(Wallets $wallets, DueWhen $dueWhen): Response
    {
        $debts = Debt::query()
            ->with(['payments' => fn ($query) => $query->orderByDesc('occurred_on')->orderByDesc('id'), 'category'])
            ->orderBy('status')
            ->orderBy('name')
            ->get()
            ->map(function (Debt $debt) use ($dueWhen): array {
                $due = $dueWhen->decorate('loans', $debt->id);

                return [
                    ...$debt->toSummaryArray(),
                    'due_mode' => $due['mode'],
                    'due_day' => $due['day'],
                    'due_label' => $due['label'],
                    'payments' => $debt->payments->map(fn (DebtPayment $payment): array => [
                        'id' => $payment->id,
                        'occurred_on' => $payment->occurred_on->toDateString(),
                        'amount' => (float) $payment->amount,
                        'installment_no' => $payment->installment_no,
                        'notes' => $payment->notes,
                        'counts_in_spending' => $payment->transaction_id !== null,
                    ])->values(),
                ];
            });

        return Inertia::render('Debts/Index', [
            'debts' => $debts,
            'wallets' => $wallets->all(),
        ]);
    }

    public function store(DebtRequest $request, DueWhen $dueWhen): RedirectResponse
    {
        $data = $request->validated();
        $debt = Debt::query()->create($request->debtAttributes());
        $dueWhen->put('loans', $debt->id, $data['due_mode'] ?? null, $data['due_day'] ?? null);

        return back()->with('success', 'Loan added.');
    }

    public function update(DebtRequest $request, Debt $debt, DueWhen $dueWhen): RedirectResponse
    {
        $data = $request->validated();
        $debt->update($request->debtAttributes());
        $dueWhen->put('loans', $debt->id, $data['due_mode'] ?? null, $data['due_day'] ?? null);
        $debt->refreshStatus();

        return back()->with('success', 'Loan updated.');
    }

    public function destroy(Debt $debt): RedirectResponse
    {
        $debt->load('payments.transaction');

        foreach ($debt->payments as $payment) {
            $payment->transaction?->delete();
        }

        $debt->delete();

        return back()->with('success', 'Loan removed.');
    }

    public function storePayment(DebtPaymentRequest $request, Debt $debt): RedirectResponse
    {
        $data = $request->validated();
        $nextInstallment = $debt->nextInstallmentNo();

        $transaction = Transaction::query()->create([
            'occurred_on' => $data['occurred_on'],
            'amount' => $data['amount'],
            'direction' => 'out',
            'type' => 'debt_payment',
            'category_id' => $debt->category_id,
            'debt_id' => $debt->id,
            'description' => 'Payment · '.$debt->name,
            'notes' => $data['notes'] ?? null,
        ]);

        $debt->payments()->create([
            'transaction_id' => $transaction->id,
            'occurred_on' => $data['occurred_on'],
            'amount' => $data['amount'],
            'installment_no' => $nextInstallment,
            'notes' => $data['notes'] ?? null,
        ]);

        $debt->refresh()->load('payments');
        $debt->refreshStatus();

        return back()->with('success', 'Payment logged.');
    }

    public function destroyPayment(Debt $debt, DebtPayment $payment): RedirectResponse
    {
        abort_unless($payment->debt_id === $debt->id, 404);

        $payment->transaction?->delete();
        $payment->delete();

        $debt->refresh()->load('payments');
        $debt->refreshStatus();

        return back()->with('success', 'Payment removed.');
    }
}
