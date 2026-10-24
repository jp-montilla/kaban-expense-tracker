<?php

namespace App\Http\Requests;

use App\Services\Wallets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DayLogRequest extends FormRequest
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
            'occurred_on' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:transactions,id'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'items.*.type' => ['required', Rule::in(['daily', 'refund'])],
            'items.*.via_credit' => ['sometimes', 'boolean'],
            'items.*.credit_method' => ['nullable', 'string', 'max:40'],
            'items.*.credit_months' => ['nullable', 'integer', Rule::in(Wallets::TERMS)],
            'items.*.credit_monthly' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('items', []) as $index => $item) {
                if (! filter_var($item['via_credit'] ?? false, FILTER_VALIDATE_BOOLEAN) || ($item['type'] ?? '') === 'refund') {
                    continue;
                }

                if (! is_string($item['credit_method'] ?? null) || $item['credit_method'] === '') {
                    $validator->errors()->add("items.{$index}.credit_method", 'Choose a credit option.');
                }

                if (! in_array((int) ($item['credit_months'] ?? 0), Wallets::TERMS, true)) {
                    $validator->errors()->add("items.{$index}.credit_months", 'Choose how many months.');
                }

                if (! is_numeric($item['credit_monthly'] ?? null) || (float) $item['credit_monthly'] < 0.01) {
                    $validator->errors()->add("items.{$index}.credit_monthly", 'Enter the monthly pay.');
                }
            }
        });
    }
}
