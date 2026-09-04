<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunInventorySkuSyncRequest extends RunInventorySyncRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'sku' => ['required', 'string', 'max:150'],
        ];
    }
}
