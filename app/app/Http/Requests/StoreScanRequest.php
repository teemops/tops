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
     * Require at least one of scan_profiles or scan_types, and — when both are given —
     * require the services to be ones the chosen profiles actually have rules for.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $profiles = $this->input('scan_profiles') ?: [];
            $services = $this->input('scan_types') ?: [];

            if (empty($profiles) && empty($services)) {
                $validator->errors()->add('scan_profiles', 'Select at least one scan group.');

                return;
            }

            if (empty($profiles) || empty($services) || !is_array($profiles) || !is_array($services)) {
                return;
            }

            // Narrowing a profile to a service it has no rules for would run a scan that
            // cannot produce a finding, and an empty result reads as "you are compliant".
            // Rejecting it is kinder than running it.
            $selectable = ScanProfilesService::selectableServicesForAll($profiles);
            $unscannable = array_values(array_diff($services, $selectable));

            if ($unscannable !== []) {
                $validator->errors()->add(
                    'scan_types',
                    sprintf(
                        'No rules exist for %s in the selected scan %s, so the scan could not return findings.',
                        implode(', ', $unscannable),
                        count($profiles) === 1 ? 'group' : 'groups'
                    )
                );
            }
        });
    }
}
