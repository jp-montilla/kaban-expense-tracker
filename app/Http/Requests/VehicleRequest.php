<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['year', 'make', 'model', 'plate'] as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'make' => ['sometimes', 'nullable', 'string', 'max:80'],
            'model' => ['sometimes', 'nullable', 'string', 'max:80'],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1950', 'max:2100'],
            'plate' => ['sometimes', 'nullable', 'string', 'max:20'],
            'current_odometer' => ['sometimes', 'required', 'integer', 'min:0', 'max:2000000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
