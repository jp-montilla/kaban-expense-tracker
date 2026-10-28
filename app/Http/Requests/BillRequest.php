<?php

namespace App\Http\Requests;

use App\Services\Wallets;
use App\Support\Ownership;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BillRequest extends FormRequest
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
        $bill = $this->route('bill');

        return [
            'name' => ['required', 'string', 'max:80'],
            'monthly_plan' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'due_mode' => ['nullable', 'string', Rule::in(['first', 'second', 'exact'])],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:28', 'required_if:due_mode,exact'],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                tap(Rule::unique('categories', 'slug')->ignore($bill), function ($unique): void {
                    if (Ownership::ready()) {
                        $unique->where('user_id', $this->user()?->id);
                    }
                }),
            ],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $name = (string) $this->input('name');
                $slug = (string) ($this->input('slug') ?: Str::slug($name));

                if (app(Wallets::class)->isCreditAccount($slug, $name)) {
                    $validator->errors()->add('name', 'That is a credit payment option. Bills and daily-log purchases charged to it are computed automatically.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $mode = $this->input('due_mode');

        if ($mode === '' || $mode === 'anytime') {
            $this->merge([
                'due_mode' => null,
                'due_day' => null,
            ]);
        }
    }
}
