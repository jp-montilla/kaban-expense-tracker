<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaryRequest;
use App\Models\Salary;
use Illuminate\Http\RedirectResponse;

class SalaryController extends Controller
{
    public function store(SalaryRequest $request): RedirectResponse
    {
        Salary::query()->create($this->payload($request));

        return back()->with('success', 'Salary saved. Months on or after that date now use it.');
    }

    public function update(SalaryRequest $request, Salary $salary): RedirectResponse
    {
        $salary->update($this->payload($request));

        return back()->with('success', 'Salary updated.');
    }

    public function destroy(Salary $salary): RedirectResponse
    {
        $salary->delete();

        return back()->with('success', 'Salary removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SalaryRequest $request): array
    {
        $data = $request->validated();
        $data['notes'] = Salary::encodeNotes($data['pay_cycle'] ?? 'cutoff', $data['notes'] ?? null);
        unset($data['pay_cycle']);

        return $data;
    }
}
