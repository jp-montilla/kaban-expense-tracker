<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class BillController extends Controller
{
    public function store(BillRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $max = (int) Category::query()->max('sort_order');

        $slug = $data['slug'] ?? Str::slug($data['name']);
        $slug = $slug !== '' ? $slug : 'bill';

        Category::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'kind' => 'bill',
            'color' => $this->color(),
            'monthly_plan' => $data['monthly_plan'],
            'sort_order' => $max + 10,
            'is_active' => true,
        ]);

        return back()->with('success', 'Bill added.');
    }

    public function update(BillRequest $request, Category $bill): RedirectResponse
    {
        abort_unless($bill->kind === 'bill', 404);

        $data = $request->validated();
        $bill->update([
            'name' => $data['name'],
            'monthly_plan' => $data['monthly_plan'],
        ]);

        return back()->with('success', 'Bill updated.');
    }

    public function destroy(Category $bill): RedirectResponse
    {
        abort_unless($bill->kind === 'bill', 404);

        if ($bill->transactions()->exists()) {
            $bill->update(['is_active' => false, 'monthly_plan' => 0]);

            return back()->with('success', 'Bill hidden from the budget. Old payments stay in the log.');
        }

        $bill->delete();

        return back()->with('success', 'Bill removed.');
    }

    private function color(): string
    {
        $colors = ['#8a5a12', '#3d6f8f', '#1f6b45', '#3f5e4a', '#6e4a6a', '#3e5c4e', '#7c4a32', '#6b5344'];

        return $colors[Category::query()->where('kind', 'bill')->count() % count($colors)];
    }
}
