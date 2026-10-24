<?php

namespace App\Http\Requests;

use App\Services\Wallets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreditChargeRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:80'],
            'months' => ['required', 'integer', Rule::in(Wallets::TERMS)],
            'monthly' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'first_due_on' => ['nullable', 'date'],
        ];
    }
}
