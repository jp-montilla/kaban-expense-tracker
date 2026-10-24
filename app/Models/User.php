<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use App\Support\Ownership;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'living_plan'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, Trashable;

    public const OWNER_EMAIL = 'johnpaul.montilla1315@gmail.com';

    public const OWNER_PASSWORD = 'KabanHouseholdpassword1111';

    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'living_plan' => 'decimal:2',
        ];
    }

    public function isSuperAdmin(): bool
    {
        if ($this->email === self::OWNER_EMAIL) {
            return true;
        }

        if (! array_key_exists('role', $this->getAttributes())) {
            return false;
        }

        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! array_key_exists('role', $this->getAttributes())) {
            return true;
        }

        return $this->role === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        if (! array_key_exists('is_active', $this->getAttributes())) {
            return true;
        }

        return $this->is_active === true;
    }

    public static function owner(): self
    {
        return self::query()->where('email', self::OWNER_EMAIL)->firstOrFail();
    }

    protected static function booted(): void
    {
        static::deleting(function (User $user): bool {
            if ($user->isSuperAdmin()) {
                return false;
            }

            $user->trashOwned();

            return true;
        });

        static::forceDeleting(function (User $user): bool {
            return ! $user->isSuperAdmin();
        });
    }

    public function delete(): ?bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        return parent::delete();
    }

    public function forceDelete(): ?bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        return parent::forceDelete();
    }

    private function trashOwned(): void
    {
        if (! Ownership::ready()) {
            return;
        }

        Vehicle::withoutGlobalScope('owned')->where('user_id', $this->id)->get()->each->delete();
        Debt::withoutGlobalScope('owned')->where('user_id', $this->id)->get()->each->delete();
        Transaction::withoutGlobalScope('owned')->where('user_id', $this->id)->delete();
        SavingsEntry::withoutGlobalScope('owned')->where('user_id', $this->id)->delete();
        Salary::withoutGlobalScope('owned')->where('user_id', $this->id)->delete();
        Bonus::withoutGlobalScope('owned')->where('user_id', $this->id)->delete();
        MonthlyIncome::withoutGlobalScope('owned')->where('user_id', $this->id)->delete();
        Category::withoutGlobalScope('owned')->where('user_id', $this->id)->delete();

        if (Ownership::hasPartVehicle()) {
            MaintenancePart::withoutGlobalScope('owned')->where('user_id', $this->id)->delete();
        }
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<Debt, $this>
     */
    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<SavingsEntry, $this>
     */
    public function savingsEntries(): HasMany
    {
        return $this->hasMany(SavingsEntry::class);
    }

    /**
     * @return HasMany<Salary, $this>
     */
    public function salaries(): HasMany
    {
        return $this->hasMany(Salary::class);
    }

    /**
     * @return HasMany<Bonus, $this>
     */
    public function bonuses(): HasMany
    {
        return $this->hasMany(Bonus::class);
    }

    /**
     * @return HasMany<MonthlyIncome, $this>
     */
    public function monthlyIncomes(): HasMany
    {
        return $this->hasMany(MonthlyIncome::class);
    }

    /**
     * @return HasMany<MaintenancePart, $this>
     */
    public function maintenanceParts(): HasMany
    {
        return $this->hasMany(MaintenancePart::class);
    }
}
