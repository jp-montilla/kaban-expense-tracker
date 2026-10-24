<?php

namespace App\Http\Requests;

use App\Support\Ownership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceLogRequest extends FormRequest
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
        $vehicle = $this->route('vehicle');

        return [
            'maintenance_part_id' => [
                'required',
                'integer',
                Rule::exists('maintenance_parts', 'id')->where(function ($query) use ($vehicle): void {
                    if (Ownership::hasPartVehicle() && $vehicle) {
                        $query->where('vehicle_id', $vehicle->id);
                    }

                    if (Ownership::ready()) {
                        $query->where('user_id', $this->user()?->id);
                    }
                }),
            ],
            'serviced_on' => ['required', 'date'],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'shop' => ['nullable', 'string', 'max:120'],
            'next_due_odo' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'next_due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'log_as_expense' => ['nullable', 'boolean'],
        ];
    }
}
