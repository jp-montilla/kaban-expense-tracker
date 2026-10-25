<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalletRequest extends FormRequest
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
        $updating = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'name' => [$updating ? 'sometimes' : 'required', 'string', 'max:40'],
            'group' => [$updating ? 'sometimes' : 'nullable', 'string', Rule::in(['wallet', 'credit', 'bank'])],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'cutoff_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'plans' => ['nullable', 'array', 'max:12'],
            'plans.*.id' => ['nullable', 'string', 'max:40'],
            'plans.*.months' => ['required_with:plans', 'integer', 'min:1', 'max:60'],
            'plans.*.interest' => ['required_with:plans', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
