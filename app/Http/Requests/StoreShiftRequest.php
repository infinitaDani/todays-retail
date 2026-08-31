<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'is_day_off' => ['nullable', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_unless:is_day_off,1'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_unless:is_day_off,1', 'different:start_time'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function passedValidation(): void
    {
        if ($this->boolean('is_day_off')) {
            $this->merge(['start_time' => null, 'end_time' => null]);
        }
    }
}
