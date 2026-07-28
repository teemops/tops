<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

/**
 * Move CloudFormation callback messages from teemops_main_dlq back onto teemops_main
 * so aws:process-sqs gets another chance at them.
 *
 * Messages land in the DLQ after maxReceiveCount (5) failed deliveries — typically
 * because the poller was wedged or the app was down, not because the message is bad.
 * Nothing logs when that happens, so a child account's stack delete can sit in
 * DELETE_IN_PROGRESS indefinitely with no trace in the app.
 *
 * Always inspect first: `php artisan aws:redrive-dlq --peek`
 */
class RedriveDlqMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:redrive-dlq
        {--peek : List what is in the DLQ without moving anything}
        {--limit=10 : Maximum number of messages to move}
        {--source= : Source queue name (defaults to TOPS_SQS_DLQ_NAME)}
        {--destination= : Destination queue name (defaults to TOPS_SQS_NAME)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Redrive dead-lettered CloudFormation callbacks from teemops_main_dlq back to teemops_main';

    /**
     * Visibility timeout applied to messages while this command holds them. Short so a
     * failed move returns the message quickly instead of hiding it for the queue default.
     */
    private const HOLD_SECONDS = 30;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sourceName = $this->option('source') ?: config('services.aws.sqs_dlq_name');
        $destinationName = $this->option('destination') ?: config('services.aws.sqs_name');

        if (!$sourceName) {
            $this->error('DLQ name missing. Set TOPS_SQS_DLQ_NAME in .env or pass --source.');
            return Command::FAILURE;
        }

        if (!$destinationName) {
            $this->error('Destination queue name missing. Set TOPS_SQS_NAME in .env or pass --destination.');
            return Command::FAILURE;
        }

        $limit = (int) $this->option('limit');
        if ($limit < 1) {
            $this->error('--limit must be at least 1.');
            return Command::FAILURE;
        }

        $peek = (bool) $this->option('peek');
        $region = config('services.aws.region', config('services.ses.region', 'us-east-1'));

        // Same timeout hardening as ProcessSqsMessages — the SDK sets none by default.
        $sqsClient = new SqsClient([
            'version' => 'latest',
            'region' => $region,
            'http' => [
                'connect_timeout' => 5,
                'timeout' => 35,
            ],
        ]);

        $sourceUrl = $this->getQueueUrl($sqsClient, $sourceName);
        if (!$sourceUrl) {
            $this->error("Could not find SQS queue: {$sourceName}");
            return Command::FAILURE;
        }

        $destinationUrl = $peek ? null : $this->getQueueUrl($sqsClient, $destinationName);
        if (!$peek && !$destinationUrl) {
            $this->error("Could not find SQS queue: {$destinationName}");
            return Command::FAILURE;
        }

        $this->info($peek
            ? "Peeking at {$sourceName} (nothing will be moved)"
            : "Redriving up to {$limit} message(s): {$sourceName} -> {$destinationName}");

        $seen = 0;
        $moved = 0;
        $failed = 0;

        // A released message becomes visible again immediately, so without tracking
        // MessageIds the loop below would re-read (and re-report) the same one until
        // it hit --limit. Peeked messages are held until the end for the same reason.
        $seenIds = [];
        $peekedHandles = [];

        while ($seen < $limit) {
            try {
                $result = $sqsClient->receiveMessage([
                    'QueueUrl' => $sourceUrl,
                    'MaxNumberOfMessages' => min(10, $limit - $seen),
                    'WaitTimeSeconds' => 2,
                    'VisibilityTimeout' => self::HOLD_SECONDS,
                    'AttributeNames' => ['All'],
                ]);
            } catch (AwsException $e) {
                $this->error('AWS SQS Error: ' . $e->getMessage());
                Log::error('DLQ redrive: receive failed', [
                    'queue_name' => $sourceName,
                    'error' => $e->getMessage(),
                    'code' => $e->getAwsErrorCode(),
                ]);
                return Command::FAILURE;
            }

            $messages = $result->get('Messages') ?? [];

            if (empty($messages)) {
                break;
            }

            $newInBatch = 0;

            foreach ($messages as $message) {
                $messageId = $message['MessageId'] ?? null;
                if ($messageId !== null && isset($seenIds[$messageId])) {
                    continue;
                }
                $seenIds[$messageId] = true;
                $newInBatch++;

                $seen++;
                $summary = $this->summarize($message);
                $this->line('  ' . $this->formatSummary($summary));

                if ($peek) {
                    $peekedHandles[] = $message['ReceiptHandle'];
                    continue;
                }

                if ($this->moveMessage($sqsClient, $message, $sourceUrl, $destinationUrl, $destinationName, $summary)) {
                    $moved++;
                } else {
                    $failed++;
                }
            }

            // Every message in the batch was one we had already reported.
            if ($newInBatch === 0) {
                break;
            }
        }

        // Release peeked messages only now, so the loop above never re-read them.
        foreach ($peekedHandles as $handle) {
            $this->releaseMessage($sqsClient, $sourceUrl, $handle);
        }

        if ($seen === 0) {
            $this->info("No messages in {$sourceName}.");
            return Command::SUCCESS;
        }

        if ($peek) {
            $this->info("{$seen} message(s) in {$sourceName}. Re-run without --peek to redrive them.");
            return Command::SUCCESS;
        }

        $this->info("Redrive complete: {$moved} moved, {$failed} failed.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Send one message to the destination queue, then delete it from the DLQ.
     *
     * Send-then-delete is deliberately at-least-once: if the delete fails the message
     * is redelivered rather than lost. CloudFormation callbacks are idempotent, and a
     * duplicate SUCCESS response is harmless.
     */
    private function moveMessage(
        SqsClient $sqsClient,
        array $message,
        string $sourceUrl,
        string $destinationUrl,
        string $destinationName,
        array $summary
    ): bool {
        try {
            $sqsClient->sendMessage([
                'QueueUrl' => $destinationUrl,
                'MessageBody' => $message['Body'],
            ]);
        } catch (AwsException $e) {
            $this->error('    Failed to send to destination: ' . $e->getMessage());
            Log::error('DLQ redrive: send failed', $summary + [
                'destination' => $destinationName,
                'error' => $e->getMessage(),
            ]);
            $this->releaseMessage($sqsClient, $sourceUrl, $message['ReceiptHandle']);
            return false;
        }

        try {
            $sqsClient->deleteMessage([
                'QueueUrl' => $sourceUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);
        } catch (AwsException $e) {
            // Already delivered to the destination, so report but do not count as failed.
            $this->warn('    Sent, but failed to delete from DLQ (will be redelivered): ' . $e->getMessage());
            Log::warning('DLQ redrive: delete failed after send', $summary + [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('DLQ redrive: message moved', $summary + [
            'destination' => $destinationName,
        ]);

        return true;
    }

    /**
     * Return a message to the queue immediately instead of waiting out HOLD_SECONDS.
     */
    private function releaseMessage(SqsClient $sqsClient, string $queueUrl, string $receiptHandle): void
    {
        try {
            $sqsClient->changeMessageVisibility([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $receiptHandle,
                'VisibilityTimeout' => 0,
            ]);
        } catch (AwsException $e) {
            // Not fatal — the message reappears once HOLD_SECONDS elapses.
            Log::warning('DLQ redrive: could not reset visibility', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pull the identifying CloudFormation fields out of an SNS-wrapped message body.
     *
     * @return array<string, mixed>
     */
    private function summarize(array $message): array
    {
        $body = json_decode($message['Body'] ?? '', true);

        $cloudFormationMessage = null;
        if (is_array($body)) {
            $cloudFormationMessage = (isset($body['Type']) && $body['Type'] === 'Notification')
                ? json_decode($body['Message'] ?? '', true)
                : $body;
        }

        $properties = $cloudFormationMessage['ResourceProperties'] ?? [];

        return [
            'message_id' => $message['MessageId'] ?? null,
            'receive_count' => $message['Attributes']['ApproximateReceiveCount'] ?? null,
            'request_type' => $cloudFormationMessage['RequestType'] ?? null,
            'request_id' => $cloudFormationMessage['RequestId'] ?? null,
            'stack_id' => $cloudFormationMessage['StackId'] ?? null,
            'unique_id' => $properties['TopsUniqueId'] ?? null,
            'external_id' => $properties['TopsExternalId'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function formatSummary(array $summary): string
    {
        $stackName = $summary['stack_id']
            ? (explode('/', $summary['stack_id'])[1] ?? $summary['stack_id'])
            : 'unknown-stack';

        return sprintf(
            '%s  stack=%s  request_id=%s  unique_id=%s  receives=%s',
            str_pad((string) ($summary['request_type'] ?? '?'), 6),
            $stackName,
            $summary['request_id'] ?? '?',
            $summary['unique_id'] ?? '?',
            $summary['receive_count'] ?? '?'
        );
    }

    /**
     * Get SQS queue URL from queue name
     */
    private function getQueueUrl(SqsClient $sqsClient, string $queueName): ?string
    {
        try {
            $result = $sqsClient->getQueueUrl(['QueueName' => $queueName]);
            return $result->get('QueueUrl');
        } catch (AwsException $e) {
            Log::error('DLQ redrive: failed to get SQS queue URL', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
