<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseTemplateRequest extends FormRequest
{
    
    public function authorize(): bool
    {
        return false;
    }

    
    public function rules(): array
    {
        return [
            
        ];
    }
}
