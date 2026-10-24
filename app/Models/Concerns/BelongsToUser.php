<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\Ownership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToUser
{
    public static function bootBelongsToUser(): void
    {
        static::addGlobalScope('owned', function (Builder $query): void {
            if (auth()->check() && Ownership::ready()) {
                $query->where($query->getModel()->getTable().'.user_id', auth()->id());
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('user_id') === null && auth()->check() && Ownership::ready()) {
                $model->setAttribute('user_id', auth()->id());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
