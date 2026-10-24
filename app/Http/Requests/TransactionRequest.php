<?php

namespace App\Http\Requests;

use App\Support\Ownership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'direction' => ['required', Rule::in(['out', 'in'])],
            'type' => ['required', Rule::in(['daily', 'bill', 'savings', 'refund'])],
            'category_id' => [
                'required',
                'integer',
                tap(Rule::exists('categories', 'id'), function ($exists): void {
                    if (Ownership::ready()) {
                        $exists->where('user_id', $this->user()?->id);
                    }
                }),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionAttributes(): array
    {
        $data = $this->validated();
        $type = $data['type'];
        $direction = match ($type) {
            'refund' => 'in',
            'savings' => $data['direction'],
            default => 'out',
        };

        return [
            'occurred_on' => $data['occurred_on'],
            'description' => $data['description'],
            'amount' => $data['amount'],
            'direction' => $direction,
            'type' => $type,
            'category_id' => $data['category_id'],
            'notes' => $data['notes'] ?? null,
        ];
    }
}
