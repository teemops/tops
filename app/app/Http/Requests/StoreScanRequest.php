<?php

namespace App\Http\Requests;

use App\Services\ScanProfilesService;
use App\Services\ScanTypesService;
use Illuminate\Contracts\Validation\Validator;
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
            // Preferred: pick one or more scan profiles (groups). Expanded to
            // scan_types + rulesets server-side.
            'scan_profiles' => ['nullable', 'array'],
            'scan_profiles.*' => ['string', ScanProfilesService::getValidationRule()],
            // Back-compat: callers may still send explicit services directly.
            'scan_types' => ['nullable', 'array'],
            'scan_types.*' => ['string', ScanTypesService::getValidationRule()],
        ];
    }

    /**
     * Require at least one of scan_profiles or scan_types.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->input('scan_profiles')) && empty($this->input('scan_types'))) {
                $validator->errors()->add('scan_profiles', 'Select at least one scan group.');
            }
        });
    }
}
