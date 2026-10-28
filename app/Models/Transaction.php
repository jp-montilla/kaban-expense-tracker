<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Concerns\Trashable;
use App\Support\CoverTag;
use App\Support\CreditTag;
use App\Support\DueTag;
use App\Support\PayTag;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'category_id', 'debt_id', 'occurred_on', 'amount', 'direction', 'type', 'description', 'notes'])]
class Transaction extends Model
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

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Debt, $this>
     */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(): array
    {
        $credit = CreditTag::parse($this->notes);

        return [
            'id' => $this->id,
            'occurred_on' => $this->occurred_on->toDateString(),
            'covers_on' => CoverTag::on($this->notes, $this->occurred_on)?->toDateString(),
            'description' => $this->description,
            'notes' => CoverTag::strip(CreditTag::strip(DueTag::strip(PayTag::strip($this->notes)))),
            'payment_method' => $credit['slug'] ?? PayTag::slug($this->notes),
            'due_id' => DueTag::id($this->notes),
            'via_credit' => $credit !== null,
            'credit_months' => $credit['months'] ?? null,
            'credit_monthly' => $credit['monthly'] ?? null,
            'amount' => (float) $this->amount,
            'direction' => $this->direction,
            'type' => $this->type,
            'category_id' => $this->category_id,
            'category' => $this->category?->name,
            'color' => $this->category?->color,
        ];
    }
}
