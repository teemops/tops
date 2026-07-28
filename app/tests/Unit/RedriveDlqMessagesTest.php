<?php

namespace Tests\Unit;

use App\Console\Commands\RedriveDlqMessages;
use Aws\Sqs\SqsClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Console\OutputStyle;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class RedriveDlqMessagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.aws.sqs_name', 'test-queue');
        Config::set('services.aws.sqs_dlq_name', 'test-queue_dlq');
        Config::set('services.aws.region', 'us-east-1');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build the command wired to a buffered output, and return both so assertions
     * can be made against what the operator would have seen on the console.
     *
     * @param array<string, mixed> $options
     * @return array{0: RedriveDlqMessages, 1: BufferedOutput}
     */
    private function createCommand(array $options = []): array
    {
        $command = new RedriveDlqMessages();
        $input = new ArrayInput($options, $command->getDefinition());
        $output = new BufferedOutput();
        $command->setOutput(new OutputStyle($input, $output));
        $command->setInput($input);

        return [$command, $output];
    }

    /**
     * An SNS-wrapped CloudFormation Delete callback, as the DLQ actually stores it.
     *
     * @return array<string, mixed>
     */
    private function dlqMessage(string $receiptHandle = 'receipt-1', int $receiveCount = 6): array
    {
        $cloudFormationMessage = [
            'RequestType' => 'Delete',
            'ResponseURL' => 'https://cloudformation-custom-resource-response.s3.amazonaws.com/test',
            'StackId' => 'arn:aws:cloudformation:us-west-2:123456789012:stack/tops-vendor-audit/abc-123',
            'RequestId' => 'test-request-id-123',
            'LogicalResourceId' => 'TopsCustomNotifier',
            'ResourceProperties' => [
                'TopsRoleArn' => 'arn:aws:iam::123456789012:role/TeemOps',
                'TopsExternalId' => 'test-external-id',
                'TopsUniqueId' => 'test-unique-id',
                'TopsType' => 'ops',
            ],
        ];

        return [
            'MessageId' => 'message-1',
            'ReceiptHandle' => $receiptHandle,
            'Body' => json_encode([
                'Type' => 'Notification',
                'Message' => json_encode($cloudFormationMessage),
            ]),
            'Attributes' => ['ApproximateReceiveCount' => (string) $receiveCount],
        ];
    }

    public function test_summarize_extracts_cloudformation_fields_from_sns_envelope(): void
    {
        [$command] = $this->createCommand();

        $method = (new \ReflectionClass($command))->getMethod('summarize');
        $method->setAccessible(true);

        $summary = $method->invoke($command, $this->dlqMessage());

        $this->assertSame('Delete', $summary['request_type']);
        $this->assertSame('test-request-id-123', $summary['request_id']);
        $this->assertSame('test-unique-id', $summary['unique_id']);
        $this->assertSame('test-external-id', $summary['external_id']);
        $this->assertSame('6', $summary['receive_count']);
    }

    public function test_summarize_tolerates_a_body_that_is_not_json(): void
    {
        [$command] = $this->createCommand();

        $method = (new \ReflectionClass($command))->getMethod('summarize');
        $method->setAccessible(true);

        $summary = $method->invoke($command, [
            'MessageId' => 'message-1',
            'ReceiptHandle' => 'receipt-1',
            'Body' => 'not json at all',
        ]);

        $this->assertNull($summary['request_type']);
        $this->assertSame('message-1', $summary['message_id']);
    }

    public function test_move_message_sends_to_destination_then_deletes_from_dlq(): void
    {
        [$command] = $this->createCommand();
        $message = $this->dlqMessage();

        $sqsClient = Mockery::mock(SqsClient::class);
        $sqsClient->shouldReceive('sendMessage')
            ->once()
            ->with([
                'QueueUrl' => 'https://sqs/destination',
                'MessageBody' => $message['Body'],
            ]);
        $sqsClient->shouldReceive('deleteMessage')
            ->once()
            ->with([
                'QueueUrl' => 'https://sqs/source',
                'ReceiptHandle' => 'receipt-1',
            ]);

        $method = (new \ReflectionClass($command))->getMethod('moveMessage');
        $method->setAccessible(true);

        $moved = $method->invoke(
            $command,
            $sqsClient,
            $message,
            'https://sqs/source',
            'https://sqs/destination',
            'test-queue',
            ['request_id' => 'test-request-id-123']
        );

        $this->assertTrue($moved);
    }

    public function test_move_message_does_not_delete_when_send_fails(): void
    {
        [$command] = $this->createCommand();
        $message = $this->dlqMessage();

        $sqsClient = Mockery::mock(SqsClient::class);
        $sqsClient->shouldReceive('sendMessage')
            ->once()
            ->andThrow(new \Aws\Exception\AwsException(
                'destination unavailable',
                new \Aws\Command('SendMessage')
            ));
        // The message must stay in the DLQ so it can be retried.
        $sqsClient->shouldNotReceive('deleteMessage');
        // ...and be released immediately rather than held for the visibility timeout.
        $sqsClient->shouldReceive('changeMessageVisibility')
            ->once()
            ->with([
                'QueueUrl' => 'https://sqs/source',
                'ReceiptHandle' => 'receipt-1',
                'VisibilityTimeout' => 0,
            ]);

        $method = (new \ReflectionClass($command))->getMethod('moveMessage');
        $method->setAccessible(true);

        $moved = $method->invoke(
            $command,
            $sqsClient,
            $message,
            'https://sqs/source',
            'https://sqs/destination',
            'test-queue',
            ['request_id' => 'test-request-id-123']
        );

        $this->assertFalse($moved);
    }

    public function test_command_fails_when_dlq_name_is_missing(): void
    {
        Config::set('services.aws.sqs_dlq_name', null);

        [$command, $output] = $this->createCommand();

        $this->assertEquals(1, $command->handle());
        $this->assertStringContainsString('DLQ name missing', $output->fetch());
    }

    public function test_command_rejects_a_limit_below_one(): void
    {
        [$command, $output] = $this->createCommand(['--limit' => '0']);

        $this->assertEquals(1, $command->handle());
        $this->assertStringContainsString('--limit must be at least 1', $output->fetch());
    }
}
