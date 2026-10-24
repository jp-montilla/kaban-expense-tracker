<?php

namespace App\Http\Controllers;

use App\Http\Requests\BonusRequest;
use App\Models\Bonus;
use Illuminate\Http\RedirectResponse;

class BonusController extends Controller
{
    public function store(BonusRequest $request): RedirectResponse
    {
        Bonus::query()->create($request->validated());

        return back()->with('success', 'Bonus added. It counts as pay on that date.');
    }

    public function update(BonusRequest $request, Bonus $bonus): RedirectResponse
    {
        $bonus->update($request->validated());

        return back()->with('success', 'Bonus updated.');
    }

    public function destroy(Bonus $bonus): RedirectResponse
    {
        $bonus->delete();

        return back()->with('success', 'Bonus removed.');
    }
}
