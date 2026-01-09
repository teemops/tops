<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitAwsAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // No validation needed for init - it just creates a pending record
        ];
    }
}
