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
     * Test validation fails when scan_types is missing
     */
    public function test_validation_fails_when_scan_types_missing(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $rules = (new StoreScanRequest())->rules();

        $validator = Validator::make([
            'aws_account_id' => $awsAccount->id,
        ], $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('scan_types'));
    }

    /**
     * Test validation fails when scan_types is empty array
     */
    public function test_validation_fails_when_scan_types_empty(): void
    {
        $organization = Organization::factory()->create();
        $awsAccount = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
        ]);

        $rules = (new StoreScanRequest())->rules();

        $validator = Validator::make([
            'aws_account_id' => $awsAccount->id,
            'scan_types' => [],
        ], $rules);

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('scan_types'));
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
