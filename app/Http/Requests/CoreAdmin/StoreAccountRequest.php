<?php

namespace App\Http\Requests\CoreAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'ruc' => ['required', 'string', 'max:20', Rule::unique('core.accounts', 'ruc')],
            'database_name' => ['required', 'string', 'max:150', Rule::unique('core.accounts', 'database_name')],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
