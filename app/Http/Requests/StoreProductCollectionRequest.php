<?php
namespace App\Http\Requests; use Illuminate\Foundation\Http\FormRequest;
class StoreProductCollectionRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['name'=>['required','string','max:150'],'reference'=>['nullable','string','max:100'],'description'=>['nullable','string'],'is_active'=>['nullable','boolean']]; } protected function prepareForValidation(): void { $this->merge(['is_active'=>$this->boolean('is_active')]); } }
