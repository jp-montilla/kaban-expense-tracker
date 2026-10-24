<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayRequest;
use App\Models\Category;
use App\Models\Debt;
use App\Models\SavingsEntry;
use App\Models\Transaction;
use App\Services\Wallets;
use App\Support\CreditTag;
use App\Support\DueTag;
use App\Support\PayTag;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class PayController extends Controller
{
    public function store(PayRequest $request, Wallets $wallets): RedirectResponse
    {
        $data = $request->validated();
        $source = $wallets->find($data['payment_method']);
        abort_unless($source !== null, 422, 'Unknown payment option.');

        if ($data['kind'] === 'credit_due') {
            abort_unless($source['group'] !== 'credit', 422, 'Pay this installment with cash, a wallet, or a bank.');
        }

        $viaCredit = filter_var($data['via_credit'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($viaCredit) {
            abort_unless($source['group'] === 'credit', 422, 'Choose a credit option.');
        }

        return match ($data['kind']) {
            'loan' => $this->payLoan($data),
            'savings_in', 'savings_out' => $this->paySavings($data),
            'credit_due' => $this->payCreditDue($data, $wallets),
            default => $this->payBill($data, $wallets, $source),
        };
    }

    public function update(PayRequest $request, Transaction $transaction, Wallets $wallets): RedirectResponse
    {
        $data = $request->validated();
        $source = $wallets->find($data['payment_method']);
        abort_unless($source !== null, 422, 'Unknown payment option.');

        return match ($data['kind']) {
            'credit_due' => $this->updateCreditDue($data, $transaction, $wallets, $source),
            'bill' => $this->updateBill($data, $transaction, $wallets, $source),
            default => abort(422, 'This payment cannot be edited here.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $source
     */
    private function payBill(array $data, Wallets $wallets, array $source): RedirectResponse
    {
        $bill = Category::query()->where('kind', 'bill')->findOrFail($data['target_id']);

        if (filter_var($data['via_credit'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $months = (int) $data['credit_months'];
            $monthly = (float) $data['credit_monthly'];
            $charge = $wallets->addCharge($source['id'], [
                'description' => $bill->name,
                'months' => $months,
                'monthly' => $monthly,
                'first_due_on' => $wallets->firstDueOn($source, $data['occurred_on']),
            ]);
            $notes = CreditTag::wrap($source['slug'], $months, $monthly, $charge['id'], $data['notes'] ?? null);

            Transaction::query()->create([
                'occurred_on' => $data['occurred_on'],
                'amount' => round($monthly * $months, 2),
                'direction' => 'out',
                'type' => 'bill',
                'category_id' => $bill->id,
                'description' => $bill->name,
                'notes' => $notes,
            ]);

            return back()->with('success', $bill->name.' charged to '.$source['name'].'.');
        }

        $notes = PayTag::wrap($data['payment_method'], $data['notes'] ?? null);

        Transaction::query()->create([
            'occurred_on' => $data['occurred_on'],
            'amount' => $data['amount'],
            'direction' => 'out',
            'type' => 'bill',
            'category_id' => $bill->id,
            'description' => $bill->name,
            'notes' => $notes,
        ]);

        return back()->with('success', $bill->name.' paid.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $source
     */
    private function updateBill(array $data, Transaction $transaction, Wallets $wallets, array $source): RedirectResponse
    {
        abort_unless($transaction->type === 'bill', 404);
        abort_if(DueTag::id($transaction->notes) !== null, 422, 'Edit this installment from Credit.');
        abort_unless((int) $transaction->category_id === (int) $data['target_id'], 422);

        $bill = Category::query()->where('kind', 'bill')->findOrFail($data['target_id']);
        $viaCredit = filter_var($data['via_credit'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($viaCredit) {
            abort_unless($source['group'] === 'credit', 422, 'Choose a credit option.');
        }

        if ($this->dropCharge($transaction, $wallets) === false) {
            return back()->with('error', 'This charge already has a paid installment.');
        }

        if ($viaCredit) {
            $months = (int) $data['credit_months'];
            $monthly = (float) $data['credit_monthly'];
            $charge = $wallets->addCharge($source['id'], [
                'description' => $bill->name,
                'months' => $months,
                'monthly' => $monthly,
                'first_due_on' => $wallets->firstDueOn($source, $data['occurred_on']),
            ]);

            $transaction->update([
                'occurred_on' => $data['occurred_on'],
                'amount' => round($monthly * $months, 2),
                'description' => $bill->name,
                'notes' => CreditTag::wrap($source['slug'], $months, $monthly, $charge['id'], $data['notes'] ?? null),
            ]);

            return back()->with('success', $bill->name.' updated.');
        }

        $transaction->update([
            'occurred_on' => $data['occurred_on'],
            'amount' => $data['amount'],
            'description' => $bill->name,
            'notes' => PayTag::wrap($data['payment_method'], $data['notes'] ?? null),
        ]);

        return back()->with('success', $bill->name.' updated.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $source
     */
    private function updateCreditDue(array $data, Transaction $transaction, Wallets $wallets, array $source): RedirectResponse
    {
        abort_unless($source['group'] !== 'credit', 422, 'Pay this installment with cash, a wallet, or a bank.');

        $dueId = DueTag::id($transaction->notes);
        abort_unless($dueId !== null && $dueId === (string) $data['target_id'], 422);

        $match = $wallets->findDue($dueId);
        abort_if($match === null, 404);
        abort_unless(($match['due']['paid_on'] ?? null) !== null, 422, 'This installment is not paid yet.');
        abort_unless((int) ($match['due']['transaction_id'] ?? 0) === $transaction->id, 422);

        $label = $match['wallet']['name'].' · '.$match['charge']['description'].' '.$match['due']['n'].'/'.$match['charge']['months'];

        $transaction->update([
            'occurred_on' => $data['occurred_on'],
            'amount' => $match['due']['amount'],
            'description' => $label,
            'notes' => DueTag::wrap($dueId, PayTag::wrap($data['payment_method'], $data['notes'] ?? null)),
        ]);

        try {
            $wallets->updateDuePaidOn($dueId, $data['occurred_on']);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $label.' updated.');
    }

    private function dropCharge(Transaction $transaction, Wallets $wallets): bool
    {
        $chargeId = CreditTag::chargeId($transaction->notes);

        if ($chargeId === null) {
            return true;
        }

        try {
            $wallets->removeChargeById($chargeId);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payLoan(array $data): RedirectResponse
    {
        $debt = Debt::query()->findOrFail($data['target_id']);
        $notes = PayTag::wrap($data['payment_method'], $data['notes'] ?? null);
        $nextInstallment = $debt->nextInstallmentNo();

        $transaction = Transaction::query()->create([
            'occurred_on' => $data['occurred_on'],
            'amount' => $data['amount'],
            'direction' => 'out',
            'type' => 'debt_payment',
            'category_id' => $debt->category_id,
            'debt_id' => $debt->id,
            'description' => 'Payment · '.$debt->name,
            'notes' => $notes,
        ]);

        $debt->payments()->create([
            'transaction_id' => $transaction->id,
            'occurred_on' => $data['occurred_on'],
            'amount' => $data['amount'],
            'installment_no' => $nextInstallment,
            'notes' => $notes,
        ]);

        $debt->refresh()->load('payments');
        $debt->refreshStatus();

        return back()->with('success', $debt->name.' paid.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function paySavings(array $data): RedirectResponse
    {
        $direction = $data['kind'] === 'savings_out' ? 'in' : 'out';
        $notes = PayTag::wrap($data['payment_method'], $data['notes'] ?? null);

        SavingsEntry::query()->create([
            'occurred_on' => $data['occurred_on'],
            'amount' => $data['amount'],
            'direction' => $direction,
            'notes' => $notes ?? ($direction === 'out' ? 'Add to savings' : 'From savings'),
        ]);

        return back()->with('success', 'Savings updated.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function payCreditDue(array $data, Wallets $wallets): RedirectResponse
    {
        $match = $wallets->findDue((string) $data['target_id']);
        abort_if($match === null, 404);
        abort_unless(($match['due']['paid_on'] ?? null) === null, 422, 'This installment is already paid.');

        $label = $match['wallet']['name'].' · '.$match['charge']['description'].' '.$match['due']['n'].'/'.$match['charge']['months'];
        $notes = DueTag::wrap($match['due']['id'], PayTag::wrap($data['payment_method'], $data['notes'] ?? null));

        $transaction = Transaction::query()->create([
            'occurred_on' => $data['occurred_on'],
            'amount' => $data['amount'],
            'direction' => 'out',
            'type' => 'bill',
            'category_id' => null,
            'description' => $label,
            'notes' => $notes,
        ]);

        try {
            $wallets->markDuePaid($match['due']['id'], $transaction->id, $data['occurred_on']);
        } catch (InvalidArgumentException $exception) {
            $transaction->delete();

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $label.' paid.');
    }
}
