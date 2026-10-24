<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'living_plan' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'budget_cycle' => ['nullable', 'in:month,cutoff'],
        ];
    }
}
