<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunInventorySyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'warehouse_id' => $this->filled('warehouse_id')
                ? $this->input('warehouse_id')
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer'],
        ];
    }
}
