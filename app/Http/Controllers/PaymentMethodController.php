<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreditChargeRequest;
use App\Http\Requests\WalletRequest;
use App\Services\Wallets;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class PaymentMethodController extends Controller
{
    public function store(WalletRequest $request, Wallets $wallets): RedirectResponse
    {
        $data = $request->validated();

        try {
            $wallets->add($data['name'], $data['group'] ?? 'wallet');
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Payment option added.');
    }

    public function update(WalletRequest $request, string $wallet, Wallets $wallets): RedirectResponse
    {
        try {
            $wallets->update($wallet, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Payment option saved.');
    }

    public function destroy(string $wallet, Wallets $wallets): RedirectResponse
    {
        try {
            $wallets->remove($wallet);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Payment option removed.');
    }

    public function storeCharge(CreditChargeRequest $request, string $wallet, Wallets $wallets): RedirectResponse
    {
        try {
            $wallets->addCharge($wallet, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Charge added to bills.');
    }

    public function destroyCharge(string $wallet, string $charge, Wallets $wallets): RedirectResponse
    {
        try {
            $wallets->removeCharge($wallet, $charge);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Charge removed.');
    }
}
