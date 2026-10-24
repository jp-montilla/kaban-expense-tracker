<?php

namespace App\Http\Requests;

use App\Support\Ownership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalaryRequest extends FormRequest
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
        $salary = $this->route('salary');

        $unique = Rule::unique('salaries', 'effective_on')->ignore($salary);

        if (Ownership::ready()) {
            $unique->where('user_id', $this->user()?->id);
        }

        return [
            'effective_on' => ['required', 'date', $unique],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'pay_cycle' => ['required', Rule::in(['cutoff', 'month'])],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
