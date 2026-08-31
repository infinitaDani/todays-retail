<?php
namespace App\Http\Requests; use Illuminate\Foundation\Http\FormRequest;
class StoreProductCategoryRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['parent_id'=>['nullable','integer','exists:tenant.product_categories,id'],'name'=>['required','string','max:150'],'description'=>['nullable','string'],'is_active'=>['nullable','boolean'],'sort_order'=>['nullable','integer','min:0']]; } protected function prepareForValidation(): void { $this->merge(['is_active'=>$this->boolean('is_active')]); } }
