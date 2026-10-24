<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\Trashable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'effective_on', 'amount', 'notes'])]
class Salary extends Model
{
    use BelongsToUser, Trashable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public static function inForceOn(CarbonImmutable $date): ?self
    {
        return static::query()
            ->whereDate('effective_on', '<=', $date->toDateString())
            ->orderByDesc('effective_on')
            ->orderByDesc('id')
            ->first();
    }

    public static function latest(): ?self
    {
        return static::query()
            ->orderByDesc('effective_on')
            ->orderByDesc('id')
            ->first();
    }

    public function payCycle(): string
    {
        if (is_string($this->notes) && str_starts_with($this->notes, '[month]')) {
            return 'month';
        }

        return 'cutoff';
    }

    public function noteText(): ?string
    {
        if ($this->notes === null) {
            return null;
        }

        $text = trim((string) preg_replace('/^\[(month|cutoff)\]\s*/', '', $this->notes));

        return $text === '' ? null : $text;
    }

    public static function encodeNotes(string $cycle, ?string $notes): string
    {
        $tag = $cycle === 'month' ? '[month]' : '[cutoff]';
        $text = trim((string) $notes);

        return $text === '' ? $tag : $tag.' '.$text;
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(): array
    {
        return [
            'id' => $this->id,
            'effective_on' => $this->effective_on->toDateString(),
            'amount' => (float) $this->amount,
            'pay_cycle' => $this->payCycle(),
            'notes' => $this->noteText(),
        ];
    }
}
