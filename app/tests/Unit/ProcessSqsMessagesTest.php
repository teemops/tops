<?php

namespace Tests\Unit;

use App\Console\Commands\ProcessSqsMessages;
use App\Models\AwsAccount;
use App\Models\Organization;
use App\Services\SnsSignatureVerifier;
use Illuminate\Support\Facades\Cache;
use Aws\Sqs\SqsClient;
use Aws\Result;
use Aws\Exception\AwsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\OutputStyle;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ProcessSqsMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration after parent::setUp() so facades are available
        Config::set('services.aws.sqs_name', 'test-queue');
        Config::set('services.aws.sqs_arn', 'arn:aws:sqs:us-east-1:123456789012:test-queue');
        Config::set('services.aws.region', 'us-east-1');
        Config::set('services.aws.account_link_window_hours', 24);

        // Every test below feeds a hand-built envelope with no real AWS signature.
        // Default to a verifier that accepts, so each test exercises the check it is
        // actually about; the signature tests override this with fakeSnsVerifier(false).
        $this->fakeSnsVerifier(true);
    }

    /**
     * Swap the SNS verifier for one with a fixed answer.
     *
     * Signature validation is covered on its own in SnsSignatureVerifierTest against
     * real AWS-signed fixtures. What matters here is only whether processMessage
     * honours the verdict, so a stub is the honest boundary.
     */
    private function fakeSnsVerifier(bool $verdict): void
    {
        $this->app->bind(SnsSignatureVerifier::class, fn () => new class($verdict) extends SnsSignatureVerifier {
            public function __construct(private bool $verdict)
            {
                // Deliberately does not call parent::__construct(): the real
                // MessageValidator would reach out to AWS for a signing certificate.
            }

            public function verifyPayload(array $payload): bool
            {
                return $this->verdict;
            }
        });
    }

    /**
     * Build an SNS-wrapped CloudFormation custom-resource message.
     *
     * Mirrors the shape of the real captured messages in references/samples/: the
     * CloudFormation request is a JSON *string* in the envelope's Message field, and
     * the account that owns the stack appears in StackId as well as in TopsRoleArn.
     */
    private function snsMessageBody(array $cloudFormationMessage): string
    {
        return json_encode([
            'Type' => 'Notification',
            'TopicArn' => 'arn:aws:sns:us-east-1:123456789012:teemops-sns',
            'Message' => json_encode($cloudFormationMessage),
        ]);
    }

    /**
     * Run processMessage against one message, asserting it is deleted from the queue.
     */
    private function processOne(string $body, string $receiptHandle = 'test-receipt-handle', bool $expectDelete = true): void
    {
        $command = $this->createCommandWithOutput();
        $method = (new \ReflectionClass($command))->getMethod('processMessage');
        $method->setAccessible(true);

        $sqsClient = Mockery::mock(SqsClient::class);
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';

        $sqsClient->shouldReceive('deleteMessage')
            ->times($expectDelete ? 1 : 0)
            ->andReturn(new Result([]));

        $method->invoke($command, [
            'ReceiptHandle' => $receiptHandle,
            'Body' => $body,
        ], $sqsClient, $queueUrl);
    }

    private function rejectionCount(string $reason): int
    {
        return (int) Cache::get(ProcessSqsMessages::REJECTION_CACHE_PREFIX . $reason, 0);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper to set up command with output
     */
    private function createCommandWithOutput(): ProcessSqsMessages
    {
        $command = new ProcessSqsMessages();
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $outputStyle = new OutputStyle($input, $output);
        $command->setOutput($outputStyle);
        return $command;
    }

    /**
     * Test that command fails when SQS configuration is missing
     */
    public function test_command_fails_when_sqs_configuration_missing(): void
    {
        Config::set('services.aws.sqs_name', null);
        Config::set('services.aws.sqs_arn', null);

        $command = $this->createCommandWithOutput();
        $result = $command->handle();

        $this->assertEquals(1, $result); // Command::FAILURE
    }

    /**
     * Test that command fails when queue URL cannot be retrieved
     * Note: This test is skipped because SqsClient is instantiated inside handle()
     * and cannot be easily mocked. Integration tests would cover this scenario.
     */
    public function test_command_fails_when_queue_url_cannot_be_retrieved(): void
    {
        $this->markTestSkipped('SqsClient instantiation cannot be easily mocked. Use integration tests.');
    }

    /**
     * Test processing a Create request successfully
     */
    public function test_process_create_request_successfully(): void
    {
        // Create organization and pending account
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->pending()->create([
            'organization_id' => $organization->id,
            'unique_id' => $organization->org_id,
            'external_id' => 'test-external-id-123',
            'name' => 'Pending AWS Account',
        ]);

        // Mock SQS message body (SNS notification format)
        $cloudFormationMessage = [
            'RequestType' => 'Create',
            'ResponseURL' => 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test',
            // Same account as TopsRoleArn below — CloudFormation sets StackId, so a
            // legitimate message always agrees with itself here.
            'StackId' => 'arn:aws:cloudformation:us-west-2:660228977852:stack/test-stack/123',
            'RequestId' => 'test-request-id-123',
            'LogicalResourceId' => 'TopsCustomNotifier',
            'PhysicalResourceId' => 'test-physical-resource-id',
            'ResourceProperties' => [
                'TopsRoleArn' => 'arn:aws:iam::660228977852:role/tops-vendor-audit-TopsAuditSetup-8dHp0GmUCDNL',
                'TopsExternalId' => 'test-external-id-123',
                'TopsUniqueId' => $organization->org_id,
                'TopsType' => 'audit',
            ],
        ];

        $snsMessage = [
            'Type' => 'Notification',
            'Message' => json_encode($cloudFormationMessage),
        ];

        $sqsMessageBody = json_encode($snsMessage);

        // Mock HTTP response for CloudFormation
        Http::fake([
            'https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200),
        ]);

        // Use reflection to call processMessage directly
        $command = $this->createCommandWithOutput();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        // Create mock SQS client and queue URL
        $sqsClient = Mockery::mock(SqsClient::class);
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';

        $message = [
            'ReceiptHandle' => 'test-receipt-handle',
            'Body' => $sqsMessageBody,
        ];

        // Mock deleteMessage call
        $sqsClient->shouldReceive('deleteMessage')
            ->once()
            ->with([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => 'test-receipt-handle',
            ])
            ->andReturn(new Result([]));

        // Process the message
        $method->invoke($command, $message, $sqsClient, $queueUrl);

        // Assert account was updated
        $account->refresh();
        $this->assertEquals('completed', $account->status);
        $this->assertEquals('660228977852', $account->aws_account_id);
        $this->assertEquals('arn:aws:iam::660228977852:role/tops-vendor-audit-TopsAuditSetup-8dHp0GmUCDNL', $account->iam_role_arn);
        $this->assertEquals('AWS Account 660228977852', $account->name);

        // Assert HTTP request was made to CloudFormation
        Http::assertSent(function ($request) {
            return $request->url() === 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test'
                && $request->method() === 'PUT'
                && $request->data()['Status'] === 'SUCCESS';
        });
    }

    /**
     * Test processing a Delete request successfully
     */
    public function test_process_delete_request_successfully(): void
    {
        // Create organization and completed account
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
            'unique_id' => $organization->org_id,
            'external_id' => 'test-external-id-456',
        ]);

        // Mock SQS message body (SNS notification format)
        $cloudFormationMessage = [
            'RequestType' => 'Delete',
            'ResponseURL' => 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test-delete',
            'StackId' => 'arn:aws:cloudformation:us-west-2:123456789012:stack/test-stack/456',
            'RequestId' => 'test-request-id-456',
            'LogicalResourceId' => 'TopsCustomNotifier',
            'PhysicalResourceId' => 'test-physical-resource-id',
            'ResourceProperties' => [
                'TopsExternalId' => 'test-external-id-456',
                'TopsUniqueId' => $organization->org_id,
            ],
        ];

        $snsMessage = [
            'Type' => 'Notification',
            'Message' => json_encode($cloudFormationMessage),
        ];

        $sqsMessageBody = json_encode($snsMessage);

        // Mock HTTP response for CloudFormation
        Http::fake([
            'https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200),
        ]);

        // Use reflection to call processMessage directly
        $command = $this->createCommandWithOutput();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        // Create mock SQS client and queue URL
        $sqsClient = Mockery::mock(SqsClient::class);
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';

        $message = [
            'ReceiptHandle' => 'test-receipt-handle-delete',
            'Body' => $sqsMessageBody,
        ];

        // Mock deleteMessage call
        $sqsClient->shouldReceive('deleteMessage')
            ->once()
            ->with([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => 'test-receipt-handle-delete',
            ])
            ->andReturn(new Result([]));

        // Process the message
        $method->invoke($command, $message, $sqsClient, $queueUrl);

        // Assert account was deleted (soft deleted)
        $this->assertSoftDeleted('aws_accounts', [
            'id' => $account->id,
        ]);

        // Assert HTTP request was made to CloudFormation with SUCCESS
        Http::assertSent(function ($request) {
            return $request->url() === 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test-delete'
                && $request->method() === 'PUT'
                && $request->data()['Status'] === 'SUCCESS';
        });
    }

    /**
     * Test processing Create request when account is not found
     */
    public function test_process_create_request_when_account_not_found(): void
    {
        $organization = Organization::factory()->create();

        // Mock SQS message body (SNS notification format)
        $cloudFormationMessage = [
            'RequestType' => 'Create',
            'ResponseURL' => 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test-not-found',
            'StackId' => 'arn:aws:cloudformation:us-west-2:660228977852:stack/test-stack/789',
            'RequestId' => 'test-request-id-789',
            'LogicalResourceId' => 'TopsCustomNotifier',
            'PhysicalResourceId' => 'test-physical-resource-id',
            'ResourceProperties' => [
                'TopsRoleArn' => 'arn:aws:iam::660228977852:role/tops-vendor-audit-TopsAuditSetup-8dHp0GmUCDNL',
                'TopsExternalId' => 'non-existent-external-id',
                'TopsUniqueId' => $organization->org_id,
            ],
        ];

        $snsMessage = [
            'Type' => 'Notification',
            'Message' => json_encode($cloudFormationMessage),
        ];

        $sqsMessageBody = json_encode($snsMessage);

        // Mock HTTP response for CloudFormation
        Http::fake([
            'https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200),
        ]);

        // Use reflection to call processMessage directly
        $command = $this->createCommandWithOutput();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        // Create mock SQS client and queue URL
        $sqsClient = Mockery::mock(SqsClient::class);
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';

        $message = [
            'ReceiptHandle' => 'test-receipt-handle-not-found',
            'Body' => $sqsMessageBody,
        ];

        // Mock deleteMessage call
        $sqsClient->shouldReceive('deleteMessage')
            ->once()
            ->with([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => 'test-receipt-handle-not-found',
            ])
            ->andReturn(new Result([]));

        // Process the message
        $method->invoke($command, $message, $sqsClient, $queueUrl);

        // Assert HTTP request was made to CloudFormation with FAILED status
        Http::assertSent(function ($request) {
            return $request->url() === 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test-not-found'
                && $request->method() === 'PUT'
                && $request->data()['Status'] === 'FAILED'
                && str_contains($request->data()['Reason'], 'Account not found');
        });
    }

    /**
     * Test processing Create request with missing required fields
     */
    public function test_process_create_request_with_missing_fields(): void
    {
        // Mock SQS message body (SNS notification format) with missing TopsRoleArn
        $cloudFormationMessage = [
            'RequestType' => 'Create',
            'ResponseURL' => 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test-missing',
            'StackId' => 'arn:aws:cloudformation:us-west-2:123456789012:stack/test-stack/999',
            'RequestId' => 'test-request-id-999',
            'LogicalResourceId' => 'TopsCustomNotifier',
            'PhysicalResourceId' => 'test-physical-resource-id',
            'ResourceProperties' => [
                // Missing TopsRoleArn
                'TopsExternalId' => 'test-external-id',
                'TopsUniqueId' => 'test-unique-id',
            ],
        ];

        $snsMessage = [
            'Type' => 'Notification',
            'Message' => json_encode($cloudFormationMessage),
        ];

        $sqsMessageBody = json_encode($snsMessage);

        // Mock HTTP response for CloudFormation
        Http::fake([
            'https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200),
        ]);

        // Use reflection to call processMessage directly
        $command = $this->createCommandWithOutput();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        // Create mock SQS client and queue URL
        $sqsClient = Mockery::mock(SqsClient::class);
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';

        $message = [
            'ReceiptHandle' => 'test-receipt-handle-missing',
            'Body' => $sqsMessageBody,
        ];

        // Mock deleteMessage call
        $sqsClient->shouldReceive('deleteMessage')
            ->once()
            ->with([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => 'test-receipt-handle-missing',
            ])
            ->andReturn(new Result([]));

        // Process the message
        $method->invoke($command, $message, $sqsClient, $queueUrl);

        // Assert HTTP request was made to CloudFormation with FAILED status
        Http::assertSent(function ($request) {
            return $request->url() === 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test-missing'
                && $request->method() === 'PUT'
                && $request->data()['Status'] === 'FAILED'
                && str_contains($request->data()['Reason'], 'Missing required fields');
        });
    }

    /**
     * Test processing invalid message format
     */
    public function test_process_invalid_message_format(): void
    {
        // Use reflection to call processMessage directly
        $command = $this->createCommandWithOutput();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        // Create mock SQS client and queue URL
        $sqsClient = Mockery::mock(SqsClient::class);
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';

        $message = [
            'ReceiptHandle' => 'test-receipt-handle-invalid',
            'Body' => 'invalid-json',
        ];

        // Mock deleteMessage call
        $sqsClient->shouldReceive('deleteMessage')
            ->once()
            ->with([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => 'test-receipt-handle-invalid',
            ])
            ->andReturn(new Result([]));

        // Process the message
        $method->invoke($command, $message, $sqsClient, $queueUrl);

        // Message should be deleted from queue (no exceptions thrown)
        $this->assertTrue(true);
    }

    /**
     * Test processing Create request with invalid Role ARN format
     */
    public function test_process_create_request_with_invalid_role_arn(): void
    {
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->pending()->create([
            'organization_id' => $organization->id,
            'unique_id' => $organization->org_id,
            'external_id' => 'test-external-id-invalid-arn',
        ]);

        // Mock SQS message body with invalid Role ARN
        $cloudFormationMessage = [
            'RequestType' => 'Create',
            'ResponseURL' => 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test-invalid-arn',
            'StackId' => 'arn:aws:cloudformation:us-west-2:123456789012:stack/test-stack/invalid',
            'RequestId' => 'test-request-id-invalid-arn',
            'LogicalResourceId' => 'TopsCustomNotifier',
            'PhysicalResourceId' => 'test-physical-resource-id',
            'ResourceProperties' => [
                'TopsRoleArn' => 'invalid-arn-format',
                'TopsExternalId' => 'test-external-id-invalid-arn',
                'TopsUniqueId' => $organization->org_id,
            ],
        ];

        $snsMessage = [
            'Type' => 'Notification',
            'Message' => json_encode($cloudFormationMessage),
        ];

        $sqsMessageBody = json_encode($snsMessage);

        // Mock HTTP response for CloudFormation
        Http::fake([
            'https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200),
        ]);

        // Use reflection to call processMessage directly
        $command = $this->createCommandWithOutput();
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        // Create mock SQS client and queue URL
        $sqsClient = Mockery::mock(SqsClient::class);
        $queueUrl = 'https://sqs.us-east-1.amazonaws.com/123456789012/test-queue';

        $message = [
            'ReceiptHandle' => 'test-receipt-handle-invalid-arn',
            'Body' => $sqsMessageBody,
        ];

        // A malformed ARN is a permanent failure: CloudFormation is told FAILED and the
        // message is removed from the queue rather than redelivered forever.
        $sqsClient->shouldReceive('deleteMessage')
            ->once()
            ->andReturn(null);

        $method->invoke($command, $message, $sqsClient, $queueUrl);

        // Assert HTTP request was made to CloudFormation with FAILED status
        Http::assertSent(function ($request) {
            return $request->url() === 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test-invalid-arn'
                && $request->method() === 'PUT'
                && $request->data()['Status'] === 'FAILED'
                && str_contains($request->data()['Reason'], 'Invalid Role ARN format');
        });

        // Account should still be pending (not updated)
        $account->refresh();
        $this->assertEquals('pending', $account->status);
    }

    // ---------------------------------------------------------------------
    // N-11 phase 1 (#100): the message body is not trusted on its face.
    // ---------------------------------------------------------------------

    /**
     * Build a valid-looking Create message, overridable per test.
     */
    private function createMessage(string $uniqueId, string $externalId, array $overrides = []): array
    {
        return array_replace([
            'RequestType' => 'Create',
            'ResponseURL' => 'https://cloudformation-custom-resource-response.s3.amazonaws.com/n11',
            'StackId' => 'arn:aws:cloudformation:us-west-2:660228977852:stack/tops-vendor-audit/abc',
            'RequestId' => 'n11-request-id',
            'LogicalResourceId' => 'TopsCustomNotifier',
            'ResourceProperties' => [
                'TopsRoleArn' => 'arn:aws:iam::660228977852:role/tops-vendor-audit-TeemOps-abc',
                'TopsExternalId' => $externalId,
                'TopsUniqueId' => $uniqueId,
                'TopsType' => 'ops',
            ],
        ], $overrides);
    }

    private function pendingAccount(array $attributes = []): AwsAccount
    {
        $organization = Organization::factory()->create();

        return AwsAccount::factory()->pending()->create(array_replace([
            'organization_id' => $organization->id,
            'unique_id' => $organization->org_id,
            'external_id' => 'n11-external-id',
            'name' => 'Pending AWS Account',
        ], $attributes));
    }

    /**
     * Gap 1 — the account is never cross-checked.
     *
     * StackId is set by CloudFormation; TopsRoleArn is set by whoever published the
     * message. When they disagree, the role ARN is pointing at an account the
     * stack-runner does not own, and linking it would have TOPS assume a role
     * chosen by the attacker.
     */
    public function test_create_is_rejected_when_role_arn_account_differs_from_stack_account(): void
    {
        $account = $this->pendingAccount();

        Http::fake(['https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200)]);

        $this->processOne($this->snsMessageBody($this->createMessage(
            $account->unique_id,
            $account->external_id,
            // Stack ran in 111111111111; the role ARN claims 660228977852.
            ['StackId' => 'arn:aws:cloudformation:us-west-2:111111111111:stack/tops-vendor-audit/abc'],
        )));

        $account->refresh();
        $this->assertSame('pending', $account->status, 'A mismatched role ARN must not link the account');
        $this->assertNull($account->iam_role_arn);
        $this->assertSame(1, $this->rejectionCount('stack_account_mismatch'));

        Http::assertSent(fn ($request) => $request->data()['Status'] === 'FAILED'
            && str_contains($request->data()['Reason'], 'does not match the account that created the stack'));
    }

    /**
     * A StackId that is not a parseable CloudFormation ARN gives us nothing to
     * compare against, so it fails closed rather than skipping the check.
     */
    public function test_create_is_rejected_when_stack_id_is_unparseable(): void
    {
        $account = $this->pendingAccount();

        Http::fake(['https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200)]);

        $this->processOne($this->snsMessageBody($this->createMessage(
            $account->unique_id,
            $account->external_id,
            ['StackId' => 'not-an-arn'],
        )));

        $this->assertSame('pending', $account->refresh()->status);
        $this->assertSame(1, $this->rejectionCount('stack_account_mismatch'));
    }

    /**
     * Gap 2 — status is not checked.
     *
     * Without this, a replayed or forged Create repoints an account that is already
     * linked, swapping the credentials the scanner uses out from under it.
     */
    public function test_create_is_rejected_when_account_is_not_pending(): void
    {
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
            'unique_id' => $organization->org_id,
            'external_id' => 'n11-already-linked',
            'iam_role_arn' => 'arn:aws:iam::999999999999:role/the-real-one',
        ]);

        Http::fake(['https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200)]);

        $this->processOne($this->snsMessageBody(
            $this->createMessage($account->unique_id, $account->external_id)
        ));

        $account->refresh();
        $this->assertSame('completed', $account->status);
        $this->assertSame(
            'arn:aws:iam::999999999999:role/the-real-one',
            $account->iam_role_arn,
            'An already-linked account must not be repointed at a new role'
        );
        $this->assertSame(1, $this->rejectionCount('account_not_pending'));

        Http::assertSent(fn ($request) => $request->data()['Status'] === 'FAILED'
            && str_contains($request->data()['Reason'], 'not awaiting linking'));
    }

    /**
     * Gap 3 — the pending window never expires.
     */
    public function test_create_is_rejected_when_pending_link_window_has_expired(): void
    {
        $account = $this->pendingAccount(['updated_at' => now()->subHours(25)]);

        Http::fake(['https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200)]);

        $this->processOne($this->snsMessageBody(
            $this->createMessage($account->unique_id, $account->external_id)
        ));

        $this->assertSame('pending', $account->refresh()->status);
        $this->assertSame(1, $this->rejectionCount('link_window_expired'));

        Http::assertSent(fn ($request) => $request->data()['Status'] === 'FAILED'
            && str_contains($request->data()['Reason'], 'has expired'));
    }

    /**
     * The expiry is measured from when the link was last issued, not when the row
     * was first created. The controller keeps one pending row per organization and
     * reuses it, so an org that started onboarding last week and comes back today
     * must not be handed a link that is already expired.
     */
    public function test_a_reissued_link_is_not_expired_by_the_original_created_at(): void
    {
        $account = $this->pendingAccount([
            'created_at' => now()->subDays(7),
            'updated_at' => now()->subMinutes(5),
        ]);

        Http::fake(['https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200)]);

        $this->processOne($this->snsMessageBody(
            $this->createMessage($account->unique_id, $account->external_id)
        ));

        $this->assertSame('completed', $account->refresh()->status);
        $this->assertSame(0, $this->rejectionCount('link_window_expired'));
    }

    public function test_create_is_accepted_inside_the_link_window(): void
    {
        $account = $this->pendingAccount(['updated_at' => now()->subHours(23)]);

        Http::fake(['https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200)]);

        $this->processOne($this->snsMessageBody(
            $this->createMessage($account->unique_id, $account->external_id)
        ));

        $this->assertSame('completed', $account->refresh()->status);
        $this->assertSame(0, $this->rejectionCount('link_window_expired'));
    }

    /**
     * An operator whose approval process runs longer than the default can turn the
     * window off, so the setting has to actually be honoured at 0.
     */
    public function test_link_window_of_zero_disables_expiry(): void
    {
        Config::set('services.aws.account_link_window_hours', 0);

        $account = $this->pendingAccount(['updated_at' => now()->subYear()]);

        Http::fake(['https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200)]);

        $this->processOne($this->snsMessageBody(
            $this->createMessage($account->unique_id, $account->external_id)
        ));

        $this->assertSame('completed', $account->refresh()->status);
    }

    /**
     * Gap 4 — the SNS signature is not verified on the SQS path.
     *
     * The message stays on the queue: a verification failure can be a transient
     * inability to fetch the signing certificate, and SQS redelivery (then the DLQ)
     * is the right shape for that. A forged message simply fails again.
     */
    public function test_message_with_an_invalid_signature_is_rejected_and_left_on_the_queue(): void
    {
        $this->fakeSnsVerifier(false);
        $account = $this->pendingAccount();

        Http::fake();

        $this->processOne(
            $this->snsMessageBody($this->createMessage($account->unique_id, $account->external_id)),
            expectDelete: false,
        );

        $this->assertSame('pending', $account->refresh()->status);
        $this->assertSame(1, $this->rejectionCount('signature_verification_failed'));
        Http::assertNothingSent();
    }

    /**
     * A body with no SNS envelope has no signature to check. The old code treated
     * that as a "direct message" and processed it anyway, which was a way straight
     * past verification.
     */
    public function test_message_without_an_sns_envelope_is_rejected(): void
    {
        $account = $this->pendingAccount();

        Http::fake();

        // The raw CloudFormation request, unwrapped.
        $this->processOne((string) json_encode(
            $this->createMessage($account->unique_id, $account->external_id)
        ));

        $this->assertSame('pending', $account->refresh()->status);
        $this->assertSame(1, $this->rejectionCount('not_an_sns_notification'));
        Http::assertNothingSent();
    }

    /**
     * The rejection counter is the part an operator can alarm on, so it has to
     * survive being read back — a Log::warning alone was the gap.
     */
    public function test_rejections_increment_a_readable_counter(): void
    {
        $account = $this->pendingAccount();

        Http::fake(['https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200)]);

        $mismatched = $this->snsMessageBody($this->createMessage(
            $account->unique_id,
            $account->external_id,
            ['StackId' => 'arn:aws:cloudformation:us-west-2:111111111111:stack/tops-vendor-audit/abc'],
        ));

        $this->processOne($mismatched, 'handle-1');
        $this->processOne($mismatched, 'handle-2');

        $this->assertSame(2, $this->rejectionCount('stack_account_mismatch'));
        $this->assertSame(2, $this->rejectionCount('total'));

        $this->artisan('aws:link-rejections')
            ->expectsOutputToContain('stack_account_mismatch')
            ->assertSuccessful();
    }

    /**
     * Update repoints a live account at a new role, so closing the gap on Create
     * alone would only move the forged-ARN path one RequestType over.
     */
    public function test_update_is_rejected_when_role_arn_account_differs_from_stack_account(): void
    {
        $organization = Organization::factory()->create();
        $account = AwsAccount::factory()->completed()->create([
            'organization_id' => $organization->id,
            'unique_id' => $organization->org_id,
            'external_id' => 'n11-update',
            'iam_role_arn' => 'arn:aws:iam::999999999999:role/the-real-one',
        ]);

        Http::fake(['https://cloudformation-custom-resource-response.s3.amazonaws.com/*' => Http::response('', 200)]);

        $this->processOne($this->snsMessageBody($this->createMessage(
            $account->unique_id,
            $account->external_id,
            [
                'RequestType' => 'Update',
                'StackId' => 'arn:aws:cloudformation:us-west-2:111111111111:stack/tops-vendor-audit/abc',
            ],
        )));

        $this->assertSame(
            'arn:aws:iam::999999999999:role/the-real-one',
            $account->refresh()->iam_role_arn,
            'A mismatched Update must not repoint the role'
        );
        $this->assertSame(1, $this->rejectionCount('stack_account_mismatch'));
    }
}
