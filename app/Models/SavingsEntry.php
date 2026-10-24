<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\Trashable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'occurred_on', 'amount', 'direction', 'notes'])]
class SavingsEntry extends Model
{
    use BelongsToUser, Trashable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public static function balanceCents(): int
    {
        return (int) static::query()->get()->sum(function (self $entry): int {
            $cents = Money::cents($entry->amount);

            return $entry->direction === 'out' ? -$cents : $cents;
        });
    }

    public function isOpening(): bool
    {
        return str_starts_with(strtolower(trim((string) $this->notes)), 'starting');
    }

    public function towardCashLeftCents(): int
    {
        if ($this->isOpening()) {
            return 0;
        }

        $cents = Money::cents($this->amount);

        return $this->direction === 'out' ? -$cents : $cents;
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(): array
    {
        return [
            'id' => $this->id,
            'occurred_on' => $this->occurred_on->toDateString(),
            'amount' => (float) $this->amount,
            'direction' => $this->direction,
            'notes' => $this->notes,
        ];
    }
}
