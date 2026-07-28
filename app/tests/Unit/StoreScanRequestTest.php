<?php

namespace Tests\Unit;

use App\Http\Requests\StoreScanRequest;
use App\Models\AwsAccount;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreScanRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test validation passes with valid scan_types
     */
    public function test_validation_passes_with_valid_scan_types(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $rules = (new StoreScanRequest())->rules();

        $validator = Validator::make([
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['iam', 's3'],
        ], $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Run full validation including the request's withValidator() cross-field check.
     */
    private function validate(array $data): \Illuminate\Contracts\Validation\Validator
    {
        // The request must carry the payload too: withValidator()'s after-hook reads
        // it back via $this->input(), so an empty request would always report
        // "Select at least one scan group" no matter what was passed in.
        $request = StoreScanRequest::create('/api/scans', 'POST', $data);
        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);
        return $validator;
    }

    /**
     * Test validation fails when neither scan_profiles nor scan_types is provided
     */
    public function test_validation_fails_when_neither_profiles_nor_types_provided(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $validator = $this->validate([
            'aws_account_id' => $awsAccount->id,
        ]);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('scan_profiles'));
    }

    /**
     * Test validation fails when both scan_profiles and scan_types are empty
     */
    public function test_validation_fails_when_selection_empty(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $validator = $this->validate([
            'aws_account_id' => $awsAccount->id,
            'scan_types' => [],
            'scan_profiles' => [],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('scan_profiles'));
    }

    /**
     * Test validation passes with a valid scan profile
     */
    public function test_validation_passes_with_valid_scan_profile(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $validator = $this->validate([
            'aws_account_id' => $awsAccount->id,
            'scan_profiles' => ['basic'],
        ]);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test validation fails with an unavailable scan profile (e.g. empty ruleset)
     */
    public function test_validation_fails_with_unavailable_scan_profile(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        // PCI is not yet available (empty ruleset), so it must be rejected.
        $validator = $this->validate([
            'aws_account_id' => $awsAccount->id,
            'scan_profiles' => ['pci'],
        ]);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('scan_profiles.0'));
    }

    /**
     * Test validation fails with invalid scan type
     */
    public function test_validation_fails_with_invalid_scan_type(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $rules = (new StoreScanRequest())->rules();

        $validator = Validator::make([
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['invalid-type'],
        ], $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('scan_types.0'));
    }

    /**
     * Test validation passes with all valid scan types
     */
    public function test_validation_passes_with_all_valid_types(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $rules = (new StoreScanRequest())->rules();

        $validator = Validator::make([
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2', 'iam', 's3'],
        ], $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test validation passes with single scan type
     */
    public function test_validation_passes_with_single_scan_type(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $rules = (new StoreScanRequest())->rules();

        $validator = Validator::make([
            'aws_account_id' => $awsAccount->id,
            'scan_types' => ['ec2'],
        ], $rules);

        $this->assertTrue($validator->passes());
    }

    /**
     * Test validation requires aws_account_id
     */
    public function test_validation_requires_aws_account_id(): void
    {
        $rules = (new StoreScanRequest())->rules();

        $validator = Validator::make([
            'scan_types' => ['iam'],
        ], $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('aws_account_id'));
    }

    /**
     * Test validation requires aws_account_id to exist
     */
    public function test_validation_requires_aws_account_id_to_exist(): void
    {
        $rules = (new StoreScanRequest())->rules();

        $validator = Validator::make([
            'aws_account_id' => 'non-existent-id',
            'scan_types' => ['iam'],
        ], $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('aws_account_id'));
    }
}
