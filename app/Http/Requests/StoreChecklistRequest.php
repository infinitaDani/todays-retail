<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChecklistRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'shift_ids' => ['required', 'array', 'min:1'],
            'shift_ids.*' => ['integer', 'distinct', Rule::exists('tenant.shifts', 'id')],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.task_id' => ['required', 'integer', 'distinct', Rule::exists('tenant.tasks', 'id')],
            'items.*.start_time' => ['required', 'date_format:H:i'],
            'items.*.due_time' => ['required', 'date_format:H:i'],
        ];
    }
}
