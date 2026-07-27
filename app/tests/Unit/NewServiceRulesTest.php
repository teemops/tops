<?php

namespace Tests\Unit;

use App\Models\Scan;
use App\Models\ScanDetail;
use App\Models\ScanResult;
use App\Services\RulesEngine\ConditionEvaluator;
use App\Services\RulesEngine\FindingsEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end validation of the CloudTrail / Lambda / KMS rules (and the new
 * quick-win checks) using the REAL basic.json ruleset and the REAL
 * ConditionEvaluator. This exercises the actual PHP condition strings so a
 * malformed condition or a bad guard is caught here rather than in production.
 */
class NewServiceRulesTest extends TestCase
{
    use RefreshDatabase;

    private function evaluate(Scan $scan, array $rulesets = ['basic']): void
    {
        $engine = new FindingsEngine(new ConditionEvaluator());
        $engine->evaluateScan($scan, $rulesets);
    }

    private function detail(Scan $scan, string $service, string $method, array $rawData, ?string $resourceId = null): void
    {
        ScanDetail::create([
            'scan_id' => $scan->id,
            'service' => $service,
            'resource_type' => 'resource',
            'resource_id' => $resourceId,
            'api_method' => $method,
            'raw_data' => $rawData,
        ]);
    }

    private function findingTypes(Scan $scan): array
    {
        return ScanResult::where('scan_id', $scan->id)->pluck('finding_type')->all();
    }

    public function test_lambda_function_url_without_auth_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'lambda', 'getFunctionUrlConfig', ['AuthType' => 'NONE'], 'my-fn');

        $this->evaluate($scan);

        $this->assertContains('tops-lambda-001', $this->findingTypes($scan));
    }

    public function test_lambda_function_url_with_iam_auth_is_not_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'lambda', 'getFunctionUrlConfig', ['AuthType' => 'AWS_IAM'], 'my-fn');

        $this->evaluate($scan);

        $this->assertNotContains('tops-lambda-001', $this->findingTypes($scan));
    }

    public function test_lambda_deprecated_runtime_is_flagged_only_on_function_detail(): void
    {
        $scan = Scan::factory()->create();
        // Per-function detail with a deprecated runtime
        $this->detail($scan, 'lambda', 'listFunctions', [
            'FunctionName' => 'legacy-fn',
            'Runtime' => 'python3.6',
            'VpcConfig' => ['VpcId' => 'vpc-1'],
        ], 'legacy-fn');
        // Aggregate detail (no FunctionName) must NOT trigger the runtime rule
        $this->detail($scan, 'lambda', 'listFunctions', [
            'Functions' => [['FunctionName' => 'legacy-fn', 'Runtime' => 'python3.6']],
        ]);

        $this->evaluate($scan);

        $findings = ScanResult::where('scan_id', $scan->id)
            ->where('finding_type', 'tops-lambda-002')
            ->get();
        $this->assertCount(1, $findings, 'Only the per-function detail should flag a deprecated runtime');
        $this->assertSame('legacy-fn', $findings->first()->resource_id);
    }

    public function test_kms_key_pending_deletion_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'kms', 'describeKey', [
            'KeyMetadata' => ['KeyState' => 'PendingDeletion', 'KeyManager' => 'CUSTOMER'],
        ], 'key-123');

        $this->evaluate($scan);

        $this->assertContains('tops-kms-001', $this->findingTypes($scan));
    }

    public function test_kms_rotation_disabled_flagged_but_error_marker_is_ignored(): void
    {
        $scan = Scan::factory()->create();
        // Rotation genuinely disabled -> flagged
        $this->detail($scan, 'kms', 'getKeyRotationStatus', ['KeyRotationEnabled' => false], 'key-off');
        // Unsupported key type stored as error marker -> must NOT be flagged
        $this->detail($scan, 'kms', 'getKeyRotationStatus', ['__error__' => true], 'key-asym');

        $this->evaluate($scan);

        $findings = ScanResult::where('scan_id', $scan->id)
            ->where('finding_type', 'tops-kms-002')
            ->get();
        $this->assertCount(1, $findings);
        $this->assertSame('key-off', $findings->first()->resource_id);
    }

    public function test_cloudtrail_log_validation_off_flagged_without_aggregate_false_positive(): void
    {
        $scan = Scan::factory()->create();
        // Per-trail detail: validation off, everything else secure
        $this->detail($scan, 'cloudtrail', 'describeTrails', [
            'Name' => 'trail-1',
            'TrailARN' => 'arn:aws:cloudtrail:us-east-1:1:trail/trail-1',
            'LogFileValidationEnabled' => false,
            'IsMultiRegionTrail' => true,
            'CloudWatchLogsLogGroupArn' => 'arn:aws:logs:...',
            'KmsKeyId' => 'arn:aws:kms:...',
        ], 'trail-1');
        // Aggregate detail (has trailList, no TrailARN) must not trigger anything
        $this->detail($scan, 'cloudtrail', 'describeTrails', [
            'trailList' => [['Name' => 'trail-1']],
        ]);

        $this->evaluate($scan);

        $types = $this->findingTypes($scan);
        $this->assertContains('tops-cloudtrail-002', $types);
        // The otherwise-secure trail must not trip the other CloudTrail rules
        $this->assertNotContains('tops-cloudtrail-003', $types);
        $this->assertNotContains('tops-cloudtrail-004', $types);
        $this->assertNotContains('tops-cloudtrail-005', $types);
    }

    public function test_cloudtrail_trail_not_logging_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'cloudtrail', 'getTrailStatus', ['IsLogging' => false], 'trail-1');

        $this->evaluate($scan);

        $this->assertContains('tops-cloudtrail-001', $this->findingTypes($scan));
    }

    public function test_iam_root_mfa_disabled_flagged_and_root_keys_absent_not_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'iam', 'getAccountSummary', [
            'SummaryMap' => ['AccountMFAEnabled' => 0, 'AccountAccessKeysPresent' => 0],
        ]);

        $this->evaluate($scan);

        $types = $this->findingTypes($scan);
        $this->assertContains('tops-iam-011', $types);
        $this->assertNotContains('tops-iam-012', $types);
    }

    public function test_iam_missing_password_policy_error_marker_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        // IamScanner returns this marker when no password policy exists (NoSuchEntity)
        $this->detail($scan, 'iam', 'getAccountPasswordPolicy', ['__error__' => true]);

        $this->evaluate($scan);

        $this->assertContains('tops-iam-013', $this->findingTypes($scan));
    }

    public function test_ec2_imdsv1_flagged_and_imdsv2_not_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'ec2', 'describeInstances', [
            'InstanceId' => 'i-weak',
            'MetadataOptions' => ['HttpTokens' => 'optional'],
        ], 'i-weak');
        $this->detail($scan, 'ec2', 'describeInstances', [
            'InstanceId' => 'i-strong',
            'MetadataOptions' => ['HttpTokens' => 'required'],
        ], 'i-strong');

        $this->evaluate($scan);

        $findings = ScanResult::where('scan_id', $scan->id)
            ->where('finding_type', 'tops-ec2-006')
            ->get();
        $this->assertCount(1, $findings);
        $this->assertSame('i-weak', $findings->first()->resource_id);
    }

    public function test_ec2_vpc_without_flow_logs_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'ec2', 'describeFlowLogs', ['FlowLogs' => []], 'vpc-1');

        $this->evaluate($scan);

        $this->assertContains('tops-ec2-007', $this->findingTypes($scan));
    }

    public function test_s3_access_logging_disabled_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        // getBucketLogging returns an empty payload when logging is disabled
        $this->detail($scan, 's3', 'getBucketLogging', [], 'my-bucket');

        $this->evaluate($scan);

        $this->assertContains('tops-s3-005', $this->findingTypes($scan));
    }

    public function test_rds_instance_encryption_flagged_per_instance_not_aggregate(): void
    {
        $scan = Scan::factory()->create();
        // Per-instance detail: unencrypted -> should flag once, keyed to the instance
        $this->detail($scan, 'rds', 'describeDBInstances', [
            'DBInstanceIdentifier' => 'db-1',
            'StorageEncrypted' => false,
        ], 'db-1');
        // Aggregate detail (has DBInstances, no DBInstanceIdentifier) must NOT flag
        $this->detail($scan, 'rds', 'describeDBInstances', [
            'DBInstances' => [['DBInstanceIdentifier' => 'db-1', 'StorageEncrypted' => false]],
        ]);

        $this->evaluate($scan);

        $findings = ScanResult::where('scan_id', $scan->id)
            ->where('finding_type', 'tops-rds-002')
            ->get();
        $this->assertCount(1, $findings, 'Encryption rule must not fire on the aggregate detail');
        $this->assertSame('db-1', $findings->first()->resource_id);
    }

    public function test_rds_cluster_backup_retention_flagged_per_cluster_not_aggregate(): void
    {
        $scan = Scan::factory()->create();
        // Per-cluster detail: short retention -> should flag once, keyed to the cluster
        $this->detail($scan, 'rds', 'describeDBClusters', [
            'DBClusterIdentifier' => 'cluster-1',
            'BackupRetentionPeriod' => 1,
        ], 'cluster-1');
        // Aggregate detail (has DBClusters, no DBClusterIdentifier) must NOT flag
        $this->detail($scan, 'rds', 'describeDBClusters', [
            'DBClusters' => [['DBClusterIdentifier' => 'cluster-1', 'BackupRetentionPeriod' => 1]],
        ]);

        $this->evaluate($scan);

        $findings = ScanResult::where('scan_id', $scan->id)
            ->where('finding_type', 'tops-rds-006')
            ->get();
        $this->assertCount(1, $findings, 'Backup retention rule must not fire on the aggregate detail');
        $this->assertSame('cluster-1', $findings->first()->resource_id);
    }

    public function test_cis_ruleset_flags_root_mfa_and_open_ssh(): void
    {
        $scan = Scan::factory()->create();
        // Root MFA disabled -> cis-1.5
        $this->detail($scan, 'iam', 'getAccountSummary', [
            'SummaryMap' => ['AccountMFAEnabled' => 0, 'AccountAccessKeysPresent' => 0],
        ]);
        // Security group open to the world on port 22 -> cis-5.2
        $this->detail($scan, 'ec2', 'describeSecurityGroups', [
            'SecurityGroups' => [[
                'GroupName' => 'web',
                'IpPermissions' => [[
                    'FromPort' => 22,
                    'ToPort' => 22,
                    'IpRanges' => [['CidrIp' => '0.0.0.0/0']],
                ]],
            ]],
        ], 'sg-1');

        $this->evaluate($scan, ['cis']);

        $types = $this->findingTypes($scan);
        $this->assertContains('cis-1.5', $types);
        $this->assertContains('cis-5.2', $types);
        // Root access keys ARE absent, so that control must not fire
        $this->assertNotContains('cis-1.4', $types);
    }

    public function test_cis_ruleset_does_not_flag_compliant_resources(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'iam', 'getAccountSummary', [
            'SummaryMap' => ['AccountMFAEnabled' => 1, 'AccountAccessKeysPresent' => 0],
        ]);
        $this->detail($scan, 'ec2', 'describeSecurityGroups', [
            'SecurityGroups' => [[
                'GroupName' => 'web',
                'IpPermissions' => [[
                    'FromPort' => 22,
                    'ToPort' => 22,
                    'IpRanges' => [['CidrIp' => '10.0.0.0/8']],
                ]],
            ]],
        ], 'sg-1');

        $this->evaluate($scan, ['cis']);

        $this->assertEmpty($this->findingTypes($scan));
    }
}
