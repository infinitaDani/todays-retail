<?php

namespace App\Http\Requests\CoreAdmin;

use App\Core\Accounts\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Account $account */
        $account = $this->route('account');

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('core.users', 'id'),
                Rule::unique('core.account_user', 'user_id')
                    ->where('account_id', $account->id),
            ],
            'role_id' => ['required', 'integer', Rule::exists('core.roles', 'id')],
        ];
    }
}
