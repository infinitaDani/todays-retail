<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class StoreKnowledgeArticleRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['title'=>['required','string','max:200'],'content'=>['required','string'],'category'=>['nullable','string','max:100'],'version'=>['required','integer','min:1'],'status'=>['required',Rule::in(['draft','published','inactive'])]]; } }
