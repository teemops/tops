<?php

namespace App\Console\Commands;

use App\Models\AwsAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

class ProcessSqsMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aws:process-sqs {--once : Process messages once and exit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll SQS queue for AWS account registration messages from CloudFormation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sqsName = config('services.aws.sqs_name');
        $sqsArn = config('services.aws.sqs_arn');

        if (!$sqsName || !$sqsArn) {
            $this->error('SQS configuration missing. Please set TOPS_SQS_NAME and TOPS_SQS_ARN in .env');
            return Command::FAILURE;
        }

        $region = config('services.aws.region', config('services.ses.region', 'us-east-1'));
        
        $sqsClient = new SqsClient([
            'version' => 'latest',
            'region' => $region,
        ]);

        $queueUrl = $this->getQueueUrl($sqsClient, $sqsName);

        if (!$queueUrl) {
            $this->error("Could not find SQS queue: {$sqsName}");
            return Command::FAILURE;
        }

        $this->info("Polling SQS queue: {$sqsName}");

        do {
            try {
                $result = $sqsClient->receiveMessage([
                    'QueueUrl' => $queueUrl,
                    'MaxNumberOfMessages' => 10,
                    'WaitTimeSeconds' => 20, // Long polling
                    'AttributeNames' => ['All'],
                ]);

                $messages = $result->get('Messages') ?? [];

                if (empty($messages)) {
                    if ($this->option('once')) {
                        $this->info('No messages found. Exiting.');
                        break;
                    }
                    continue;
                }

                foreach ($messages as $message) {
                    $this->processMessage($message, $sqsClient, $queueUrl);
                }
            } catch (AwsException $e) {
                $this->error('AWS SQS Error: ' . $e->getMessage());
                Log::error('SQS polling error', [
                    'error' => $e->getMessage(),
                    'code' => $e->getAwsErrorCode(),
                ]);
                
                // Wait before retrying
                sleep(5);
            } catch (\Exception $e) {
                $this->error('Error processing SQS messages: ' . $e->getMessage());
                Log::error('SQS processing error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } while (!$this->option('once'));

        return Command::SUCCESS;
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
            Log::error('Failed to get SQS queue URL', [
                'queue_name' => $queueName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Process a single SQS message
     */
    private function processMessage(array $message, SqsClient $sqsClient, string $queueUrl): void
    {
        $receiptHandle = $message['ReceiptHandle'];
        $body = json_decode($message['Body'], true);

        if (!$body) {
            $this->warn('Invalid message body, deleting from queue');
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
            return;
        }

        // Handle SNS message format (SNS forwards to SQS)
        // The Message field contains the CloudFormation custom resource request (JSON-encoded string)
        $cloudFormationMessage = null;
        if (isset($body['Type']) && $body['Type'] === 'Notification') {
            // SNS notification wrapper - extract the Message field which is JSON-encoded
            $cloudFormationMessage = json_decode($body['Message'], true);
        } else {
            // Direct message (shouldn't happen but handle it)
            $cloudFormationMessage = $body;
        }

        if (!$cloudFormationMessage) {
            $this->warn('Invalid CloudFormation message format, deleting from queue');
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
            return;
        }

        // Extract CloudFormation request details
        $requestType = $cloudFormationMessage['RequestType'] ?? null; // Create or Delete
        $responseUrl = $cloudFormationMessage['ResponseURL'] ?? null;
        $stackId = $cloudFormationMessage['StackId'] ?? null;
        $requestId = $cloudFormationMessage['RequestId'] ?? null;
        $logicalResourceId = $cloudFormationMessage['LogicalResourceId'] ?? null;
        $physicalResourceId = $cloudFormationMessage['PhysicalResourceId'] ?? null;

        // Extract ResourceProperties
        $resourceProperties = $cloudFormationMessage['ResourceProperties'] ?? [];
        $roleArn = $resourceProperties['TopsRoleArn'] ?? null;
        $externalId = $resourceProperties['TopsExternalId'] ?? null;
        $uniqueId = $resourceProperties['TopsUniqueId'] ?? null;
        $topsType = $resourceProperties['TopsType'] ?? null;

        if (!$requestType || !$responseUrl || !$stackId || !$requestId) {
            $this->warn('Message missing required CloudFormation fields, deleting from queue');
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
            return;
        }

        // Handle Delete requests
        if ($requestType === 'Delete') {
            $this->handleDeleteRequest($uniqueId, $externalId, $responseUrl, $stackId, $requestId, $logicalResourceId, $physicalResourceId);
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
            return;
        }

        // Handle Create requests
        if ($requestType === 'Create') {
            if (!$roleArn || !$externalId || !$uniqueId) {
                $this->warn('Create message missing required fields (TopsRoleArn, TopsExternalId, TopsUniqueId), deleting from queue');
                $this->sendCloudFormationResponse($responseUrl, 'FAILED', 'Missing required fields in ResourceProperties', $stackId, $requestId, $logicalResourceId, $physicalResourceId);
                $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
                return;
            }

            // Find account by unique_id and external_id
            // Note: unique_id is derived from orgId, so we need both to match
            $account = AwsAccount::where('unique_id', $uniqueId)
                ->where('external_id', $externalId)
                ->first();

            if (!$account) {
                Log::warning('SQS message: Account not found', [
                    'unique_id' => $uniqueId,
                    'external_id' => $externalId,
                    'request_type' => $requestType,
                ]);
                $this->warn("Account not found for unique_id: {$uniqueId}, external_id: {$externalId}");
                // Send FAILED response to CloudFormation
                $this->sendCloudFormationResponse($responseUrl, 'FAILED', "Account not found for unique_id: {$uniqueId}", $stackId, $requestId, $logicalResourceId, $physicalResourceId);
                // Delete message even if account not found (prevents reprocessing)
                $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
                return;
            }

            // Extract AWS Account ID from Role ARN
            // Format: arn:aws:iam::123456789012:role/TeemOps
            preg_match('/arn:aws:iam::(\d+):role\/(.+)/', $roleArn, $matches);
            $awsAccountId = $matches[1] ?? null;

            if (!$awsAccountId) {
                $this->error("Could not extract AWS Account ID from Role ARN: {$roleArn}");
                $this->sendCloudFormationResponse($responseUrl, 'FAILED', "Invalid Role ARN format: {$roleArn}", $stackId, $requestId, $logicalResourceId, $physicalResourceId);
                return;
            }

            // Update account
            $account->update([
                'iam_role_arn' => $roleArn,
                'aws_account_id' => $awsAccountId,
                'name' => $account->name === 'Pending AWS Account' 
                    ? "AWS Account {$awsAccountId}" 
                    : $account->name,
                'status' => 'completed',
            ]);

            $this->info("AWS account {$account->id} updated to completed status");

            Log::info('AWS account activated via SQS message', [
                'account_id' => $account->id,
                'aws_account_id' => $awsAccountId,
                'unique_id' => $uniqueId,
                'external_id' => $externalId,
                'request_type' => $requestType,
            ]);

            // Send SUCCESS response to CloudFormation
            $this->sendCloudFormationResponse($responseUrl, 'SUCCESS', 'Successfully processed the request', $stackId, $requestId, $logicalResourceId, $physicalResourceId);

            // Delete message from queue after successful processing
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
        } else {
            $this->warn("Unknown RequestType: {$requestType}, deleting from queue");
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
        }
    }

    /**
     * Handle Delete request from CloudFormation
     */
    private function handleDeleteRequest(?string $uniqueId, ?string $externalId, string $responseUrl, string $stackId, string $requestId, ?string $logicalResourceId, ?string $physicalResourceId): void
    {
        if (!$uniqueId || !$externalId) {
            $this->warn('Delete request missing unique_id or external_id');
            $this->sendCloudFormationResponse($responseUrl, 'SUCCESS', 'Delete request processed (no account found)', $stackId, $requestId, $logicalResourceId, $physicalResourceId);
            return;
        }

        // Find and delete the account
        $account = AwsAccount::where('unique_id', $uniqueId)
            ->where('external_id', $externalId)
            ->first();

        if ($account) {
            $account->delete();
            $this->info("AWS account {$account->id} deleted via CloudFormation Delete request");
            Log::info('AWS account deleted via SQS message', [
                'account_id' => $account->id,
                'unique_id' => $uniqueId,
                'external_id' => $externalId,
            ]);
        }

        // Always send SUCCESS for Delete (even if account not found)
        $this->sendCloudFormationResponse($responseUrl, 'SUCCESS', 'Delete request processed', $stackId, $requestId, $logicalResourceId, $physicalResourceId);
    }

    /**
     * Send response to CloudFormation custom resource ResponseURL
     */
    private function sendCloudFormationResponse(string $responseUrl, string $status, string $reason, string $stackId, string $requestId, ?string $logicalResourceId, ?string $physicalResourceId): void
    {
        try {
            $response = [
                'Status' => $status,
                'Reason' => $reason,
                'StackId' => $stackId,
                'RequestId' => $requestId,
                'LogicalResourceId' => $logicalResourceId,
                'PhysicalResourceId' => $physicalResourceId,
            ];

            if ($status === 'SUCCESS') {
                $response['Data'] = [
                    'process' => 'teemopsStatus',
                ];
            }

            Http::timeout(10)->put($responseUrl, $response);

            $this->info("Sent {$status} response to CloudFormation");
            Log::info('CloudFormation response sent', [
                'status' => $status,
                'request_id' => $requestId,
                'stack_id' => $stackId,
            ]);
        } catch (\Exception $e) {
            $this->error("Failed to send CloudFormation response: {$e->getMessage()}");
            Log::error('Failed to send CloudFormation response', [
                'error' => $e->getMessage(),
                'response_url' => $responseUrl,
                'status' => $status,
            ]);
        }
    }

    /**
     * Delete message from SQS queue
     */
    private function deleteMessage(SqsClient $sqsClient, string $queueUrl, string $receiptHandle): void
    {
        try {
            $sqsClient->deleteMessage([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);
        } catch (AwsException $e) {
            Log::error('Failed to delete SQS message', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
