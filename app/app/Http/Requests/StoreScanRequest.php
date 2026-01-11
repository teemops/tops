<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScanRequest extends FormRequest
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
            'aws_account_id' => ['required', 'string', 'exists:aws_accounts,id'],
            'scan_types' => ['required', 'array', 'min:1'],
            'scan_types.*' => ['required', 'string', 'in:ec2,iam,s3'],
        ];
    }
}
