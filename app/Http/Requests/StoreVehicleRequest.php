<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'year' => $this->input('year') === '' ? null : $this->input('year'),
            'make' => $this->input('make') === '' ? null : $this->input('make'),
            'model' => $this->input('model') === '' ? null : $this->input('model'),
            'plate' => $this->input('plate') === '' ? null : $this->input('plate'),
            'seed_bike_parts' => $this->boolean('seed_bike_parts'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'make' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'plate' => ['nullable', 'string', 'max:20'],
            'current_odometer' => ['required', 'integer', 'min:0', 'max:2000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'seed_bike_parts' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function vehicleAttributes(): array
    {
        $data = $this->validated();
        unset($data['seed_bike_parts']);

        return $data;
    }
}
