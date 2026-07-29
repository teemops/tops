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
 * The pilot services (DynamoDB, SQS, SNS, ELBv2) were added as tasks.json files alone,
 * with no PHP. These tests run their rules through the REAL basic.json and the REAL
 * ConditionEvaluator, so a malformed condition or a bad guard fails here rather than in
 * production — the same approach NewServiceRulesTest takes for the original services.
 */
class PilotServiceRulesTest extends TestCase
{
    use RefreshDatabase;

    private function evaluate(Scan $scan): void
    {
        (new FindingsEngine(new ConditionEvaluator()))->evaluateScan($scan, ['basic']);
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

    public function test_unencrypted_sqs_queue_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'sqs', 'getQueueAttributes', [
            'Attributes' => ['QueueArn' => 'arn:aws:sqs:us-east-1:1:q', 'SqsManagedSseEnabled' => 'false'],
        ], 'q');

        $this->evaluate($scan);

        $this->assertContains('tops-sqs-001', $this->findingTypes($scan));
    }

    public function test_sqs_queue_with_sse_or_kms_is_not_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'sqs', 'getQueueAttributes', [
            'Attributes' => ['SqsManagedSseEnabled' => 'true'],
        ], 'managed');
        $this->detail($scan, 'sqs', 'getQueueAttributes', [
            'Attributes' => ['KmsMasterKeyId' => 'alias/aws/sqs'],
        ], 'kms');

        $this->evaluate($scan);

        $this->assertNotContains('tops-sqs-001', $this->findingTypes($scan));
    }

    /**
     * A failed API call is stored as the engine's error marker and reaches conditions as
     * false. That must not read as "no encryption configured" — an unreachable queue is
     * unknown, not insecure.
     */
    public function test_an_sqs_api_error_is_not_reported_as_missing_encryption(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'sqs', 'getQueueAttributes', [
            '__error__' => true,
            'error_message' => 'AccessDenied',
        ], 'q');

        $this->evaluate($scan);

        $this->assertNotContains('tops-sqs-001', $this->findingTypes($scan));
    }

    public function test_unencrypted_sns_topic_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'sns', 'getTopicAttributes', [
            'Attributes' => ['TopicArn' => 'arn:aws:sns:us-east-1:1:t'],
        ], 'arn:aws:sns:us-east-1:1:t');

        $this->evaluate($scan);

        $this->assertContains('tops-sns-001', $this->findingTypes($scan));
    }

    public function test_encrypted_sns_topic_is_not_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'sns', 'getTopicAttributes', [
            'Attributes' => ['KmsMasterKeyId' => 'alias/aws/sns'],
        ], 't');

        $this->evaluate($scan);

        $this->assertNotContains('tops-sns-001', $this->findingTypes($scan));
    }

    public function test_dynamodb_without_point_in_time_recovery_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'dynamodb', 'describeContinuousBackups', [
            'ContinuousBackupsDescription' => [
                'ContinuousBackupsStatus' => 'ENABLED',
                'PointInTimeRecoveryDescription' => ['PointInTimeRecoveryStatus' => 'DISABLED'],
            ],
        ], 'orders');

        $this->evaluate($scan);

        $this->assertContains('tops-dynamodb-001', $this->findingTypes($scan));
    }

    public function test_dynamodb_with_point_in_time_recovery_is_not_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'dynamodb', 'describeContinuousBackups', [
            'ContinuousBackupsDescription' => [
                'PointInTimeRecoveryDescription' => ['PointInTimeRecoveryStatus' => 'ENABLED'],
            ],
        ], 'orders');

        $this->evaluate($scan);

        $this->assertNotContains('tops-dynamodb-001', $this->findingTypes($scan));
    }

    public function test_dynamodb_deletion_protection_is_flagged_per_table(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'dynamodb', 'describeTable', [
            'Table' => ['TableName' => 'orders', 'DeletionProtectionEnabled' => false],
        ], 'orders');
        $this->detail($scan, 'dynamodb', 'describeTable', [
            'Table' => ['TableName' => 'customers', 'DeletionProtectionEnabled' => true],
        ], 'customers');

        $this->evaluate($scan);

        $findings = ScanResult::where('scan_id', $scan->id)
            ->where('finding_type', 'tops-dynamodb-002')
            ->pluck('resource_id')
            ->all();

        $this->assertSame(['orders'], $findings);
    }

    /**
     * The aggregate listTables row must not itself produce a finding — it has no Table
     * key, so the rule's guard has to hold it off.
     */
    public function test_the_aggregate_list_row_does_not_produce_a_dynamodb_finding(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'dynamodb', 'listTables', ['TableNames' => ['orders', 'customers']]);

        $this->evaluate($scan);

        $this->assertSame([], $this->findingTypes($scan));
    }

    public function test_plaintext_http_listener_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'elbv2', 'describeListeners', [
            'Listeners' => [
                ['Protocol' => 'HTTP', 'Port' => 80],
                ['Protocol' => 'HTTPS', 'Port' => 443],
            ],
        ], 'arn:aws:elasticloadbalancing:us-east-1:1:loadbalancer/app/web/1');

        $this->evaluate($scan);

        $this->assertContains('tops-elbv2-001', $this->findingTypes($scan));
    }

    public function test_https_only_listeners_are_not_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'elbv2', 'describeListeners', [
            'Listeners' => [['Protocol' => 'HTTPS', 'Port' => 443]],
        ], 'lb');

        $this->evaluate($scan);

        $this->assertNotContains('tops-elbv2-001', $this->findingTypes($scan));
    }

    public function test_load_balancer_without_access_logs_is_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'elbv2', 'describeLoadBalancerAttributes', [
            'Attributes' => [
                ['Key' => 'access_logs.s3.enabled', 'Value' => 'false'],
                ['Key' => 'idle_timeout.timeout_seconds', 'Value' => '60'],
            ],
        ], 'lb');

        $this->evaluate($scan);

        $this->assertContains('tops-elbv2-002', $this->findingTypes($scan));
    }

    public function test_load_balancer_with_access_logs_is_not_flagged(): void
    {
        $scan = Scan::factory()->create();
        $this->detail($scan, 'elbv2', 'describeLoadBalancerAttributes', [
            'Attributes' => [['Key' => 'access_logs.s3.enabled', 'Value' => 'true']],
        ], 'lb');

        $this->evaluate($scan);

        $this->assertNotContains('tops-elbv2-002', $this->findingTypes($scan));
    }
}
