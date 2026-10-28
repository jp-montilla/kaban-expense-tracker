<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\Trashable;
use App\Support\CoverTag;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'category_id', 'name', 'creditor', 'item', 'principal', 'opening_paid', 'installment_amount', 'installment_count', 'status', 'notes'])]
class Debt extends Model
{
    use BelongsToUser, Trashable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'opening_paid' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'installment_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Debt $debt): void {
            $debt->payments()->with('transaction')->each(function (DebtPayment $payment): void {
                $payment->transaction?->delete();
                $payment->delete();
            });
        });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<DebtPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function paidCents(): int
    {
        $fromPayments = $this->relationLoaded('payments')
            ? (int) $this->payments->sum(fn (DebtPayment $payment): int => Money::cents($payment->amount))
            : Money::cents((string) $this->payments()->sum('amount'));

        return Money::cents($this->opening_paid) + $fromPayments;
    }

    public function balanceCents(): int
    {
        return Money::cents($this->principal) - $this->paidCents();
    }

    public function refreshStatus(): void
    {
        $this->update([
            'status' => $this->balanceCents() <= 0 ? 'paid' : 'active',
        ]);
    }

    public function isMonthly(): bool
    {
        return $this->installment_count !== null && $this->installment_amount !== null;
    }

    public function spentIn(CarbonImmutable $start, CarbonImmutable $end): float
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        return round((float) $payments
            ->filter(fn (DebtPayment $payment): bool => CoverTag::inWindow(
                $payment->notes,
                $payment->occurred_on?->toImmutable(),
                $start,
                $end,
            ))
            ->sum(fn (DebtPayment $payment): float => (float) $payment->amount), 2);
    }

    public function nextInstallmentNo(): ?int
    {
        if (! $this->isMonthly()) {
            return null;
        }

        $each = Money::cents($this->installment_amount);
        $fromOpening = $each > 0 ? intdiv(Money::cents($this->opening_paid), $each) : 0;
        $fromRows = (int) $this->payments()->max('installment_no');

        return max($fromOpening, $fromRows) + 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        $paid = $this->paidCents();
        $principal = Money::cents($this->principal);
        $balance = $principal - $paid;
        $logged = $this->relationLoaded('payments') ? $this->payments->count() : $this->payments()->count();
        $each = $this->installment_amount === null ? 0 : Money::cents($this->installment_amount);
        $fromOpening = $each > 0 ? intdiv(Money::cents($this->opening_paid), $each) : 0;
        $paidInstallments = $fromOpening + $logged;
        $remaining = $this->installment_count === null ? null : max(0, $this->installment_count - $paidInstallments);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'creditor' => $this->creditor,
            'item' => $this->item,
            'status' => $balance <= 0 ? 'paid' : $this->status,
            'principal' => Money::pesos($principal),
            'paid' => Money::pesos($paid),
            'balance' => Money::pesos($balance),
            'progress' => $principal > 0 ? round(min(100, ($paid / $principal) * 100), 1) : 0,
            'is_monthly' => $this->isMonthly(),
            'installment_amount' => $this->installment_amount === null ? null : (float) $this->installment_amount,
            'installment_count' => $this->installment_count,
            'installments_logged' => $logged,
            'installments_paid' => $paidInstallments,
            'installments_remaining' => $remaining,
            'category_id' => $this->category_id,
            'notes' => $this->notes,
        ];
    }
}
