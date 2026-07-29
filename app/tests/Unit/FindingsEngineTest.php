<?php

namespace Tests\Unit;

use App\Models\Scan;
use App\Models\ScanDetail;
use App\Models\ScanResult;
use App\Services\RulesEngine\ConditionEvaluator;
use App\Services\RulesEngine\FindingsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class FindingsEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that evaluateScan processes rules and creates findings when conditions are met
     */
    public function test_evaluate_scan_creates_findings_when_conditions_met(): void
    {
        // Create a scan
        $scan = Scan::factory()->create();

        // Create scan details with MFA devices data (empty array - should trigger finding)
        $scanDetail = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-123',
            'api_method' => 'listMFADevices',
            'raw_data' => [
                'MFADevices' => [],
            ],
        ]);

        // Mock rules file
        $mockRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-001',
                    'name' => 'IAM User MFA Token',
                    'service' => 'iam',
                    'method' => 'listMFADevices',
                    'description' => 'IAM Users should have MFA enabled',
                    'severity' => 'high',
                    'condition' => "count(\$data['MFADevices'] ?? []) == 0",
                ],
            ],
        ];

        File::shouldReceive('exists')
            ->once()
            ->with(\Mockery::pattern('/basic\.json$/'))
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->with(\Mockery::pattern('/basic\.json$/'))
            ->andReturn(json_encode($mockRules));

        // Mock ConditionEvaluator to return true (condition met)
        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')
            ->once()
            ->with("count(\$data['MFADevices'] ?? []) == 0", ['MFADevices' => []])
            ->andReturn(true);

        // Create FindingsEngine with mocked evaluator
        $engine = new FindingsEngine($mockEvaluator);

        // Execute
        $engine->evaluateScan($scan, ['basic']);

        // Assert finding was created
        $this->assertDatabaseHas('scan_results', [
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-123',
            'finding_type' => 'tops-iam-001',
            'severity' => 'high',
            'status' => 'open',
        ]);

        $finding = ScanResult::where('scan_id', $scan->id)->first();
        $this->assertNotNull($finding);
        $this->assertStringContainsString('IAM User MFA Token', $finding->title);
        $this->assertStringContainsString('test-user-123', $finding->title);
        $this->assertStringContainsString('IAM Users should have MFA enabled', $finding->description);
    }

    /**
     * Test that evaluateScan does not create findings when conditions are not met
     */
    public function test_evaluate_scan_does_not_create_findings_when_conditions_not_met(): void
    {
        // Create a scan
        $scan = Scan::factory()->create();

        // Create scan details with MFA devices data (has MFA - should not trigger finding)
        $scanDetail = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-456',
            'api_method' => 'listMFADevices',
            'raw_data' => [
                'MFADevices' => [
                    ['SerialNumber' => 'arn:aws:iam::123456789012:mfa/test-user'],
                ],
            ],
        ]);

        // Mock rules file
        $mockRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-001',
                    'name' => 'IAM User MFA Token',
                    'service' => 'iam',
                    'method' => 'listMFADevices',
                    'description' => 'IAM Users should have MFA enabled',
                    'severity' => 'high',
                    'condition' => "count(\$data['MFADevices'] ?? []) == 0",
                ],
            ],
        ];

        File::shouldReceive('exists')
            ->once()
            ->with(\Mockery::pattern('/basic\.json$/'))
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->with(\Mockery::pattern('/basic\.json$/'))
            ->andReturn(json_encode($mockRules));

        // Mock ConditionEvaluator to return false (condition not met)
        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')
            ->once()
            ->with("count(\$data['MFADevices'] ?? []) == 0", ['MFADevices' => [['SerialNumber' => 'arn:aws:iam::123456789012:mfa/test-user']]])
            ->andReturn(false);

        // Create FindingsEngine with mocked evaluator
        $engine = new FindingsEngine($mockEvaluator);

        // Execute
        $engine->evaluateScan($scan, ['basic']);

        // Assert no finding was created
        $this->assertDatabaseMissing('scan_results', [
            'scan_id' => $scan->id,
        ]);
    }

    /**
     * Test that evaluateScan handles multiple scan details for the same rule
     */
    public function test_evaluate_scan_handles_multiple_scan_details(): void
    {
        // Create a scan
        $scan = Scan::factory()->create();

        // Create multiple scan details
        $scanDetail1 = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-1',
            'api_method' => 'listMFADevices',
            'raw_data' => ['MFADevices' => []],
        ]);

        $scanDetail2 = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-2',
            'api_method' => 'listMFADevices',
            'raw_data' => ['MFADevices' => []],
        ]);

        // Mock rules file
        $mockRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-001',
                    'name' => 'IAM User MFA Token',
                    'service' => 'iam',
                    'method' => 'listMFADevices',
                    'description' => 'IAM Users should have MFA enabled',
                    'severity' => 'high',
                    'condition' => "count(\$data['MFADevices'] ?? []) == 0",
                ],
            ],
        ];

        File::shouldReceive('exists')
            ->once()
            ->with(\Mockery::pattern('/basic\.json$/'))
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->with(\Mockery::pattern('/basic\.json$/'))
            ->andReturn(json_encode($mockRules));

        // Mock ConditionEvaluator to return true for both
        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')
            ->twice()
            ->andReturn(true);

        // Create FindingsEngine with mocked evaluator
        $engine = new FindingsEngine($mockEvaluator);

        // Execute
        $engine->evaluateScan($scan, ['basic']);

        // Assert two findings were created
        $findings = ScanResult::where('scan_id', $scan->id)->get();
        $this->assertCount(2, $findings);
        $this->assertEquals('test-user-1', $findings[0]->resource_id);
        $this->assertEquals('test-user-2', $findings[1]->resource_id);
    }

    /**
     * Test that evaluateScan handles multiple rulesets
     */
    public function test_evaluate_scan_handles_multiple_rulesets(): void
    {
        // Create a scan
        $scan = Scan::factory()->create();

        // Create scan details
        $scanDetail = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-789',
            'api_method' => 'listMFADevices',
            'raw_data' => ['MFADevices' => []],
        ]);

        // Mock rules files for both rulesets
        $basicRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-001',
                    'name' => 'IAM User MFA Token',
                    'service' => 'iam',
                    'method' => 'listMFADevices',
                    'description' => 'IAM Users should have MFA enabled',
                    'severity' => 'high',
                    'condition' => "count(\$data['MFADevices'] ?? []) == 0",
                ],
            ],
        ];

        $pciRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-002',
                    'name' => 'IAM User Access Keys',
                    'service' => 'iam',
                    'method' => 'listAccessKeys',
                    'description' => 'Access Keys should not be used',
                    'severity' => 'medium',
                    'condition' => "count(\$data['AccessKeyMetadata'] ?? []) > 0",
                ],
            ],
        ];

        File::shouldReceive('exists')
            ->twice()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->with(\Mockery::pattern('/basic\.json$/'))
            ->andReturn(json_encode($basicRules));

        File::shouldReceive('get')
            ->once()
            ->with(\Mockery::pattern('/pci\.json$/'))
            ->andReturn(json_encode($pciRules));

        // Mock ConditionEvaluator
        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(true);

        // Create FindingsEngine with mocked evaluator
        $engine = new FindingsEngine($mockEvaluator);

        // Execute with multiple rulesets
        $engine->evaluateScan($scan, ['basic', 'pci']);

        // Assert finding was created
        $this->assertDatabaseHas('scan_results', [
            'scan_id' => $scan->id,
            'finding_type' => 'tops-iam-001',
        ]);
    }

    /**
     * Test that evaluateScan handles missing ruleset files gracefully
     */
    public function test_evaluate_scan_handles_missing_ruleset_gracefully(): void
    {
        // Create a scan
        $scan = Scan::factory()->create();

        // Create scan details
        $scanDetail = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-999',
            'api_method' => 'listMFADevices',
            'raw_data' => ['MFADevices' => []],
        ]);

        // Mock rules file to not exist
        File::shouldReceive('exists')
            ->once()
            ->with(\Mockery::pattern('/nonexistent\.json$/'))
            ->andReturn(false);

        // Mock Log facade to verify warning is logged
        Log::shouldReceive('warning')
            ->once()
            ->with(
                \Mockery::pattern('/Failed to load ruleset/'),
                \Mockery::on(function ($context) use ($scan) {
                    return isset($context['scan_id']) && $context['scan_id'] === $scan->id;
                })
            );

        // Mock ConditionEvaluator (should not be called)
        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldNotReceive('evaluate');

        // Create FindingsEngine with mocked evaluator
        $engine = new FindingsEngine($mockEvaluator);

        // Execute - should not throw exception
        $engine->evaluateScan($scan, ['nonexistent']);

        // Assert no finding was created
        $this->assertDatabaseMissing('scan_results', [
            'scan_id' => $scan->id,
        ]);
    }

    /**
     * Test that evaluateScan prevents duplicate findings
     */
    public function test_evaluate_scan_prevents_duplicate_findings(): void
    {
        // Create a scan
        $scan = Scan::factory()->create();

        // Create an existing finding
        ScanResult::create([
            'scan_id' => $scan->id,
            'severity' => 'high',
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-dup',
            'finding_type' => 'tops-iam-001',
            'title' => 'Existing Finding',
            'description' => 'Existing description',
            'remediation' => 'Existing remediation',
            'status' => 'open',
        ]);

        // Create scan details
        $scanDetail = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-dup',
            'api_method' => 'listMFADevices',
            'raw_data' => ['MFADevices' => []],
        ]);

        // Mock rules file
        $mockRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-001',
                    'name' => 'IAM User MFA Token',
                    'service' => 'iam',
                    'method' => 'listMFADevices',
                    'description' => 'IAM Users should have MFA enabled',
                    'severity' => 'high',
                    'condition' => "count(\$data['MFADevices'] ?? []) == 0",
                ],
            ],
        ];

        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->andReturn(json_encode($mockRules));

        // Mock ConditionEvaluator to return true
        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(true);

        // Create FindingsEngine with mocked evaluator
        $engine = new FindingsEngine($mockEvaluator);

        // Execute
        $engine->evaluateScan($scan, ['basic']);

        // Assert only one finding exists (no duplicate created)
        $findings = ScanResult::where('scan_id', $scan->id)
            ->where('finding_type', 'tops-iam-001')
            ->get();
        $this->assertCount(1, $findings);
        $this->assertEquals('Existing Finding', $findings->first()->title);
    }

    /**
     * Titles are built from the rule name and the resource id. The access-key-id
     * variant only applies to rules whose id contains "access_key" — see
     * test_evaluate_scan_puts_the_access_key_id_in_the_title.
     */
    public function test_evaluate_scan_handles_access_key_age_calculation(): void
    {
        // Create a scan
        $scan = Scan::factory()->create();

        // Create scan details with access key data (old key)
        $oldDate = now()->subDays(100)->toIso8601String();
        $scanDetail = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-keys',
            'api_method' => 'listAccessKeys',
            'raw_data' => [
                'AccessKeyMetadata' => [
                    [
                        'AccessKeyId' => 'AKIAIOSFODNN7EXAMPLE',
                        'CreateDate' => $oldDate,
                    ],
                ],
            ],
        ]);

        // Mock rules file
        $mockRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-002',
                    'name' => 'IAM User Access Keys',
                    'service' => 'iam',
                    'method' => 'listAccessKeys',
                    'description' => 'Access Keys should not be used',
                    'severity' => 'medium',
                    'remediation' => 'Remove access keys and use IAM Identity Center for Single Sign-on.',
                    'condition' => "count(\$data['AccessKeyMetadata'] ?? []) > 0",
                ],
            ],
        ];

        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->andReturn(json_encode($mockRules));

        // Mock ConditionEvaluator to return true
        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')
            ->once()
            ->andReturn(true);

        // Create FindingsEngine with mocked evaluator
        $engine = new FindingsEngine($mockEvaluator);

        // Execute
        $engine->evaluateScan($scan, ['basic']);

        $finding = ScanResult::where('scan_id', $scan->id)->first();
        $this->assertNotNull($finding);
        $this->assertEquals('IAM User Access Keys - test-user-keys', $finding->title);
        $this->assertEquals('medium', $finding->severity);
        // The rule's own remediation reaches the finding — no PHP lookup involved, which
        // is what lets a contributor ship a complete rule as JSON alone.
        $this->assertEquals(
            'Remove access keys and use IAM Identity Center for Single Sign-on.',
            $finding->remediation
        );
    }

    /**
     * Rules whose id contains "access_key" get the offending key id in the title
     * instead of the resource id.
     */
    public function test_evaluate_scan_puts_the_access_key_id_in_the_title(): void
    {
        $scan = Scan::factory()->create();

        ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-keys',
            'api_method' => 'listAccessKeys',
            'raw_data' => [
                'AccessKeyMetadata' => [
                    [
                        'AccessKeyId' => 'AKIAIOSFODNN7EXAMPLE',
                        'CreateDate' => now()->subDays(100)->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $mockRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-access_key-age',
                    'name' => 'Old Access Key',
                    'service' => 'iam',
                    'method' => 'listAccessKeys',
                    'description' => 'Access keys should be rotated.',
                    'severity' => 'high',
                    'condition' => "count(\$data['AccessKeyMetadata'] ?? []) > 0",
                ],
            ],
        ];

        File::shouldReceive('exists')->once()->andReturn(true);
        File::shouldReceive('get')->once()->andReturn(json_encode($mockRules));

        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')->once()->andReturn(true);

        (new FindingsEngine($mockEvaluator))->evaluateScan($scan, ['basic']);

        $finding = ScanResult::where('scan_id', $scan->id)->first();
        $this->assertNotNull($finding);
        $this->assertEquals('Old Access Key - AKIAIOSFODNN7EXAMPLE', $finding->title);
    }

    /**
     * Rules that also mention "days" get the key's age appended to the description.
     */
    public function test_evaluate_scan_appends_access_key_age_to_the_description(): void
    {
        $scan = Scan::factory()->create();

        ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-keys',
            'api_method' => 'listAccessKeys',
            'raw_data' => [
                'AccessKeyMetadata' => [
                    [
                        'AccessKeyId' => 'AKIAIOSFODNN7EXAMPLE',
                        'CreateDate' => now()->subDays(100)->toIso8601String(),
                    ],
                ],
            ],
        ]);

        $mockRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-access_key-age',
                    'name' => 'Old Access Key',
                    'service' => 'iam',
                    'method' => 'listAccessKeys',
                    'description' => 'Access key for {resource} is stale.',
                    'severity' => 'high',
                    'condition' => "\$data['days'] > 90",
                ],
            ],
        ];

        File::shouldReceive('exists')->once()->andReturn(true);
        File::shouldReceive('get')->once()->andReturn(json_encode($mockRules));

        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')->once()->andReturn(true);

        (new FindingsEngine($mockEvaluator))->evaluateScan($scan, ['basic']);

        $finding = ScanResult::where('scan_id', $scan->id)->first();
        $this->assertNotNull($finding);
        $this->assertStringContainsString('Access key for test-user-keys is stale.', $finding->description);
        $this->assertStringContainsString('days old.', $finding->description);
    }

    /**
     * Test that evaluateScan handles evaluation errors gracefully
     */
    public function test_evaluate_scan_handles_evaluation_errors_gracefully(): void
    {
        // Create a scan
        $scan = Scan::factory()->create();

        // Create scan details
        $scanDetail = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-error',
            'api_method' => 'listMFADevices',
            'raw_data' => ['MFADevices' => []],
        ]);

        // Mock rules file
        $mockRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-001',
                    'name' => 'IAM User MFA Token',
                    'service' => 'iam',
                    'method' => 'listMFADevices',
                    'description' => 'IAM Users should have MFA enabled',
                    'severity' => 'high',
                    'condition' => "count(\$data['MFADevices'] ?? []) == 0",
                ],
            ],
        ];

        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->andReturn(json_encode($mockRules));

        // Mock ConditionEvaluator to throw exception
        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')
            ->once()
            ->andThrow(new \Exception('Evaluation error'));

        // Mock Log facade to verify warning is logged
        Log::shouldReceive('warning')
            ->once()
            ->with(
                \Mockery::pattern('/Rule evaluation failed/'),
                \Mockery::on(function ($context) use ($scan) {
                    return isset($context['scan_id']) 
                        && $context['scan_id'] === $scan->id
                        && isset($context['error']);
                })
            );

        // Create FindingsEngine with mocked evaluator
        $engine = new FindingsEngine($mockEvaluator);

        // Execute - should not throw exception
        $engine->evaluateScan($scan, ['basic']);

        // Assert no finding was created due to error
        $this->assertDatabaseMissing('scan_results', [
            'scan_id' => $scan->id,
        ]);
    }

    /**
     * Test that evaluateScan filters scan details by service and method
     */
    public function test_evaluate_scan_filters_by_service_and_method(): void
    {
        // Create a scan
        $scan = Scan::factory()->create();

        // Create scan details for different services/methods
        $matchingDetail = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 'iam',
            'resource_type' => 'user',
            'resource_id' => 'test-user-match',
            'api_method' => 'listMFADevices',
            'raw_data' => ['MFADevices' => []],
        ]);

        $nonMatchingDetail = ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => 's3',
            'resource_type' => 'bucket',
            'resource_id' => 'test-bucket',
            'api_method' => 'getPublicAccessBlock',
            'raw_data' => ['BlockPublicAcls' => true],
        ]);

        // Mock rules file (only IAM rule)
        $mockRules = [
            'rules' => [
                [
                    'rule' => 'tops-iam-001',
                    'name' => 'IAM User MFA Token',
                    'service' => 'iam',
                    'method' => 'listMFADevices',
                    'description' => 'IAM Users should have MFA enabled',
                    'severity' => 'high',
                    'condition' => "count(\$data['MFADevices'] ?? []) == 0",
                ],
            ],
        ];

        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->andReturn(json_encode($mockRules));

        // Mock ConditionEvaluator - should only be called once for matching detail
        $mockEvaluator = Mockery::mock(ConditionEvaluator::class);
        $mockEvaluator->shouldReceive('evaluate')
            ->once()
            ->with("count(\$data['MFADevices'] ?? []) == 0", ['MFADevices' => []])
            ->andReturn(true);

        // Create FindingsEngine with mocked evaluator
        $engine = new FindingsEngine($mockEvaluator);

        // Execute
        $engine->evaluateScan($scan, ['basic']);

        // Assert only one finding was created (for matching detail)
        $findings = ScanResult::where('scan_id', $scan->id)->get();
        $this->assertCount(1, $findings);
        $this->assertEquals('test-user-match', $findings->first()->resource_id);
    }

    /**
     * A rule that declares no remediation still gets the generic advice rather than an
     * empty column — the behaviour rules without an entry in the old PHP map always had.
     */
    public function test_a_rule_without_remediation_falls_back_to_generic_advice(): void
    {
        $method = new \ReflectionMethod(FindingsEngine::class, 'buildRemediation');
        $method->setAccessible(true);

        $engine = new FindingsEngine(Mockery::mock(ConditionEvaluator::class));
        $detail = new ScanDetail();

        $this->assertSame(
            'Review and remediate the security issue according to AWS best practices.',
            $method->invoke($engine, ['rule' => 'tops-whatever-001'], $detail)
        );
        $this->assertSame(
            'Do the thing.',
            $method->invoke($engine, ['rule' => 'tops-whatever-001', 'remediation' => 'Do the thing.'], $detail)
        );
    }

    /**
     * Every rule that had remediation text before it moved out of PHP must still have it.
     * A backfill that dropped entries would silently downgrade findings to generic advice.
     */
    public function test_shipped_rulesets_carry_their_remediation_text(): void
    {
        $engine = new FindingsEngine(Mockery::mock(ConditionEvaluator::class));

        $withRemediation = collect($engine->loadRules('basic'))
            ->filter(fn (array $rule) => !empty($rule['remediation']));

        $this->assertGreaterThanOrEqual(29, $withRemediation->count());
        $this->assertNotEmpty(
            $withRemediation->firstWhere('rule', 'tops-kms-002')['remediation'] ?? null
        );
    }
}
