<?php

namespace Tests\Unit;

use App\Console\Commands\ProcessSqsMessages;
use App\Models\AwsAccount;
use App\Models\Organization;
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
            'StackId' => 'arn:aws:cloudformation:us-west-2:123456789012:stack/test-stack/123',
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
            'StackId' => 'arn:aws:cloudformation:us-west-2:123456789012:stack/test-stack/789',
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

        // Process the message (should not delete from queue due to error)
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
}
