<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class StoreKnowledgeArticleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:tenant.knowledge_categories,id'],
            'requires_confirmation' => ['nullable', 'boolean'],
            'audience' => ['nullable', 'array'],
            'audience.*' => [Rule::in(['all', 'management', 'store_admin', 'advisor'])],
        ];
    }
}
