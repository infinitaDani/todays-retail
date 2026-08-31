<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class StoreBranchRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['name'=>['required','string','max:150'],'code'=>['nullable','string','max:50'],'status'=>['required',Rule::in(['active','inactive'])]]; } }
