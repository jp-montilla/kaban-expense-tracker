<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PayController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\SavingsController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/summary', SummaryController::class)->name('summary');
    Route::get('/log', [TransactionController::class, 'index'])->name('log');
    Route::post('/log', [TransactionController::class, 'storeDay'])->name('log.store');
    Route::put('/log/{day}', [TransactionController::class, 'syncDay'])->name('log.sync')->where('day', '\d{4}-\d{2}-\d{2}');
    Route::delete('/log/{day}', [TransactionController::class, 'destroyDay'])->name('log.destroy')->where('day', '\d{4}-\d{2}-\d{2}');
    Route::get('/bills', [TransactionController::class, 'bills'])->name('bills');
    Route::post('/pay', [PayController::class, 'store'])->name('pay.store');
    Route::put('/pay/{transaction}', [PayController::class, 'update'])->name('pay.update');
    Route::post('/wallets', [PaymentMethodController::class, 'store'])->name('wallets.store');
    Route::put('/wallets/{wallet}', [PaymentMethodController::class, 'update'])->name('wallets.update');
    Route::delete('/wallets/{wallet}', [PaymentMethodController::class, 'destroy'])->name('wallets.destroy');
    Route::post('/wallets/{wallet}/charges', [PaymentMethodController::class, 'storeCharge'])->name('wallets.charges.store');
    Route::delete('/wallets/{wallet}/charges/{charge}', [PaymentMethodController::class, 'destroyCharge'])->name('wallets.charges.destroy');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    Route::get('/loans', [DebtController::class, 'index'])->name('loans.index');
    Route::get('/debts', [DebtController::class, 'index'])->name('debts.index');
    Route::post('/debts', [DebtController::class, 'store'])->name('debts.store');
    Route::put('/debts/{debt}', [DebtController::class, 'update'])->name('debts.update');
    Route::delete('/debts/{debt}', [DebtController::class, 'destroy'])->name('debts.destroy');
    Route::post('/debts/{debt}/payments', [DebtController::class, 'storePayment'])->name('debts.payments.store');
    Route::delete('/debts/{debt}/payments/{payment}', [DebtController::class, 'destroyPayment'])->name('debts.payments.destroy');
    Route::get('/budget', [BudgetController::class, 'edit'])->name('budget.edit');
    Route::put('/budget', [BudgetController::class, 'update'])->name('budget.update');
    Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
    Route::put('/bills/{bill}', [BillController::class, 'update'])->name('bills.update');
    Route::delete('/bills/{bill}', [BillController::class, 'destroy'])->name('bills.destroy');
    Route::post('/bonuses', [BonusController::class, 'store'])->name('bonuses.store');
    Route::put('/bonuses/{bonus}', [BonusController::class, 'update'])->name('bonuses.update');
    Route::delete('/bonuses/{bonus}', [BonusController::class, 'destroy'])->name('bonuses.destroy');
    Route::get('/savings', [SavingsController::class, 'index'])->name('savings.index');
    Route::post('/savings', [SavingsController::class, 'store'])->name('savings.store');
    Route::put('/savings/{entry}', [SavingsController::class, 'update'])->name('savings.update');
    Route::delete('/savings/{entry}', [SavingsController::class, 'destroy'])->name('savings.destroy');
    Route::post('/salaries', [SalaryController::class, 'store'])->name('salaries.store');
    Route::put('/salaries/{salary}', [SalaryController::class, 'update'])->name('salaries.update');
    Route::delete('/salaries/{salary}', [SalaryController::class, 'destroy'])->name('salaries.destroy');
    Route::get('/bike', fn () => redirect()->route('vehicles.index'))->name('bike');
    Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
    Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
    Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
    Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
    Route::post('/vehicles/{vehicle}/parts', [VehicleController::class, 'storePart'])->name('vehicles.parts.store');
    Route::put('/vehicles/{vehicle}/parts/{part}', [VehicleController::class, 'updatePart'])->name('vehicles.parts.update');
    Route::delete('/vehicles/{vehicle}/parts/{part}', [VehicleController::class, 'destroyPart'])->name('vehicles.parts.destroy');
    Route::post('/vehicles/{vehicle}/logs', [VehicleController::class, 'storeLog'])->name('vehicles.logs.store');
    Route::delete('/vehicles/{vehicle}/logs/{log}', [VehicleController::class, 'destroyLog'])->name('vehicles.logs.destroy');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::middleware('admin')->group(function () {
        Route::get('/people', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/people', [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('/people/{user}', [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('/people/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    });
});
