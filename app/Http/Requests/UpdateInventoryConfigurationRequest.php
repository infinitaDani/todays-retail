<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'manages_warehouses' => $this->boolean('manages_warehouses'),
            'contifico_stock_sync_enabled' => $this->boolean(
                'contifico_stock_sync_enabled',
            ),
            'contifico_is_active' => $this->boolean('contifico_is_active'),
            'automatic_sync_enabled' => $this->boolean(
                'automatic_sync_enabled',
            ),
            'manual_bulk_syncs_per_day' => $this->nullableInteger(
                'manual_bulk_syncs_per_day',
            ),
			'manual_bulk_min_interval_minutes' => $this->nullableInteger(
				'manual_bulk_min_interval_minutes',
			),
        ]);
    }

    public function rules(): array
    {
        return [
            'manages_warehouses' => ['required', 'boolean'],
            'contifico_stock_sync_enabled' => ['required', 'boolean'],
            'contifico_is_active' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'automatic_sync_enabled' => ['required', 'boolean'],
            'sync_interval_minutes' => [
                'required',
                'integer',
                Rule::in([15, 30, 60, 180, 360, 720, 1440]),
            ],
            'batch_size' => ['required', 'integer', 'between:1,500'],
            'manual_bulk_syncs_per_day' => [
                'nullable',
                'integer',
                'min:0',
            ],
			'manual_bulk_min_interval_minutes' => [
				'nullable',
				'integer',
				'min:0',
			],
            'user_limits' => ['nullable', 'array'],
            'user_limits.*' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function nullableInteger(string $key): ?int
    {
        $value = $this->input($key);

        return $value === null || $value === ''
            ? null
            : (int) $value;
    }

    protected function failedValidation(Validator $validator)
    {
        $this->request->remove('api_key');

        parent::failedValidation($validator);
    }
}
