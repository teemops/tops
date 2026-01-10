<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAwsAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'aws_account_id' => ['required', 'string', 'regex:/^\d{12}$/'],
            'iam_role_arn' => ['required', 'string', 'regex:/^arn:aws:iam::\d{12}:role\/.+$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'aws_account_id.regex' => 'AWS Account ID must be exactly 12 digits.',
            'iam_role_arn.regex' => 'IAM Role ARN must be a valid ARN format (arn:aws:iam::ACCOUNT_ID:role/ROLE_NAME).',
        ];
    }
}
