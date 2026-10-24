<?php

namespace App\Http\Requests;

use App\Models\Debt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

class DebtRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'creditor' => ['nullable', 'string', 'max:120'],
            'item' => ['nullable', 'string', 'max:120'],
            'principal' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'is_monthly' => ['sometimes', 'boolean'],
            'installment_amount' => ['required_if:is_monthly,true', 'nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'installment_count' => ['required_if:is_monthly,true', 'nullable', 'integer', 'min:1', 'max:600'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function debtAttributes(): array
    {
        $data = $this->validated();
        $monthly = array_key_exists('is_monthly', $data)
            ? (bool) $data['is_monthly']
            : (($data['installment_count'] ?? null) !== null);
        $existing = $this->route('debt');

        $attributes = [
            'name' => $data['name'],
            'creditor' => $data['creditor'] ?? null,
            'principal' => $data['principal'],
            'opening_paid' => $existing instanceof Debt ? $existing->opening_paid : 0,
            'installment_amount' => $monthly ? ($data['installment_amount'] ?? null) : null,
            'installment_count' => $monthly ? ($data['installment_count'] ?? null) : null,
            'category_id' => $existing instanceof Debt ? $existing->category_id : null,
            'notes' => $data['notes'] ?? null,
        ];

        if (Schema::hasColumn('debts', 'item')) {
            $attributes['item'] = $data['item'] ?? null;
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_monthly')) {
            $this->merge([
                'is_monthly' => $this->boolean('is_monthly'),
            ]);
        }

        if ($this->boolean('is_monthly') && $this->filled('installment_amount') && $this->filled('installment_count')) {
            $this->merge([
                'principal' => round((float) $this->input('installment_amount') * (int) $this->input('installment_count'), 2),
            ]);
        }
    }
}
