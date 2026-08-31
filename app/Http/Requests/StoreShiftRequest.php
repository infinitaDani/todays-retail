<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest; use Illuminate\Validation\Rule;
class StoreShiftRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['name'=>['required','string','max:100'],'start_time'=>['required','date_format:H:i'],'end_time'=>['required','date_format:H:i','different:start_time'],'status'=>['required',Rule::in(['active','inactive'])]]; } }
