<?php

namespace App\Services;

use App\Models\Category;
use App\Models\User;

class AccountStarter
{
    public function provision(User $user): void
    {
        $this->category($user, 'daily', [
            'name' => 'Living',
            'kind' => 'variable',
            'color' => '#3f5e4a',
            'sort_order' => 10,
        ]);

        $this->category($user, 'vehicle', [
            'name' => 'Vehicle',
            'kind' => 'vehicle',
            'color' => '#3a4a38',
            'sort_order' => 20,
        ]);
    }

    /**
     * @param  array{name: string, kind: string, color: string, sort_order: int}  $attributes
     */
    private function category(User $user, string $slug, array $attributes): Category
    {
        return Category::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'user_id' => $user->id,
                'slug' => $slug,
            ],
            [
                ...$attributes,
                'monthly_plan' => 0,
                'is_active' => true,
            ],
        );
    }
}
