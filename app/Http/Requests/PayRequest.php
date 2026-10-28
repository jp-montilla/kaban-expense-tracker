<?php

namespace App\Http\Requests;

use App\Services\Wallets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayRequest extends FormRequest
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
        $viaCredit = $this->boolean('via_credit');

        return [
            'kind' => ['required', Rule::in(['bill', 'loan', 'savings_in', 'savings_out', 'credit_due'])],
            'target_id' => [
                'required',
                Rule::when($this->input('kind') === 'credit_due', ['uuid'], ['integer']),
            ],
            'occurred_on' => ['required', 'date'],
            'covers_on' => ['nullable', 'date'],
            'amount' => [$viaCredit ? 'nullable' : 'required', 'numeric', 'min:0.01', 'max:99999999'],
            'payment_method' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'via_credit' => ['sometimes', 'boolean'],
            'credit_months' => [$viaCredit ? 'required' : 'nullable', 'integer', Rule::in(Wallets::TERMS)],
            'credit_monthly' => [$viaCredit ? 'required' : 'nullable', 'numeric', 'min:0.01', 'max:99999999'],
        ];
    }
}
