<?php

namespace App\Http\Requests\CoreAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $account = $this->route('account');

        return [
            'name' => ['required', 'string', 'max:150'],
            'ruc' => ['required', 'string', 'max:20', Rule::unique('core.accounts', 'ruc')->ignore($account)],
            'database_name' => ['required', 'string', 'max:150', Rule::unique('core.accounts', 'database_name')->ignore($account)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
