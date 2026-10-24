<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaintenancePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'interval_km' => $this->input('interval_km') === '' ? null : $this->input('interval_km'),
            'interval_months' => $this->input('interval_months') === '' ? null : $this->input('interval_months'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'interval_km' => ['nullable', 'integer', 'min:1', 'max:2000000'],
            'interval_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ];
    }
}
