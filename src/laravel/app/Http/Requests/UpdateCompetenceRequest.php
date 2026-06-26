<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompetenceRequest extends FormRequest
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
