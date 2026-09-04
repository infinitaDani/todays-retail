<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'purposes' => array_values(
				array_filter(
					(array) $this->input('purposes', []),
					fn ($purpose): bool => is_string($purpose) && $purpose !== '',
				),
			),
            'contifico_code' => $this->normalizedNullableString(
                'contifico_code',
            ),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        $warehouse = $this->route('warehouse');
        $branchId = $this->integer('branch_id');

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('tenant.branches', 'id'),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('tenant.warehouses', 'name')
                    ->where('branch_id', $branchId)
                    ->ignore($warehouse?->id),
            ],
            'purposes' => ['nullable', 'array'],
			'purposes.*' => [
				'string',
				Rule::in([
					'purchase',
					'sale',
					'production',
				]),
			],
            'contifico_code' => [
                'nullable',
                'string',
                'max:120',
                Rule::unique('tenant.warehouses', 'contifico_code')
                    ->ignore($warehouse?->id),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function normalizedNullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
