<?php

namespace App\Console\Commands;

use App\Models\AwsAccount;
use App\Services\SnsSignatureVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

class ProcessSqsMessages extends Command
{
    /**
     * Stable log field so rejections can be picked out of the stream by name
     * rather than by matching on message text that is free to change.
     */
    public const REJECTION_EVENT = 'aws_account_link_rejected';

    /** Cache key prefix for the rejection counters. Read by `aws:link-rejections`. */
    public const REJECTION_CACHE_PREFIX = 'tops:aws:link-rejections:';

    /**
     * Counters live for 30 days from the *first* rejection, not the last — the TTL
     * is set by the Cache::add that seeds the key and is not refreshed by the
     * increments that follow. So each key is a rolling 30-day window that then
     * starts over, which is what an operator wants from an alarm signal: a problem
     * fixed two months ago stops showing up as a live number on its own.
     */
    public const REJECTION_CACHE_TTL = 60 * 60 * 24 * 30;

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
        
        // The SDK's default 'legacy' defaults_mode sets no HTTP timeouts at all, so a
        // long-poll whose TCP connection is silently dropped blocks this process forever
        // (supervisord can't detect it — the process stays alive and never logs again).
        // 'timeout' must exceed WaitTimeSeconds below or every long-poll aborts.
        $sqsClient = new SqsClient([
            'version' => 'latest',
            'region' => $region,
            'http' => [
                'connect_timeout' => 5,
                'timeout' => 35,
            ],
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

        // N-11: verify the envelope before anything inside it is trusted.
        //
        // The queue policy already restricts SendMessage to our topic ARN, so this
        // is defence in depth rather than the only control — but the HTTP callback
        // has verified since N-6 and this path had nothing at all, which made the
        // queue the weaker of the two ways into the same account-linking code.
        //
        // The previous "direct message (shouldn't happen but handle it)" fallback is
        // gone deliberately. Nothing legitimate reaches this queue except through the
        // SNS subscription, and a body with no envelope has no signature to check —
        // keeping it would have left an unsigned path straight past the check below.
        if (($body['Type'] ?? null) !== 'Notification') {
            $this->recordRejection('not_an_sns_notification', [
                'type' => $body['Type'] ?? null,
            ]);
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
            return;
        }

        // Resolved from the container so tests can substitute the validator, the
        // same way the HTTP callback in AwsAccountsController does.
        if (!app(SnsSignatureVerifier::class)->verifyPayload($body)) {
            // The verifier logs why. Leave the message on the queue: a verification
            // failure can be a transient inability to fetch the signing certificate,
            // and redelivery (then the DLQ after maxReceiveCount) is the right shape
            // for that. A genuinely forged message simply fails again.
            $this->recordRejection('signature_verification_failed', [
                'topic_arn' => $body['TopicArn'] ?? null,
            ]);
            return;
        }

        // The Message field contains the CloudFormation custom resource request
        // (a JSON-encoded string).
        $cloudFormationMessage = json_decode($body['Message'] ?? '', true);

        if (!$cloudFormationMessage) {
            $this->warn('Invalid CloudFormation message format, deleting from queue');
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
            return;
        }

        // Extract CloudFormation request details
        $requestType = $cloudFormationMessage['RequestType'] ?? null; // Create, Update, or Delete
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
        // TopsType is deliberately not read. It distinguished "ops" from "audit", and
        // the audit template that sent "audit" was deleted in #102; nothing branched
        // on it even while that template existed. Messages still carry the field.

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
            $this->handleCreateRequest($roleArn, $uniqueId, $externalId, $responseUrl, $stackId, $requestId, $logicalResourceId, $physicalResourceId);
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
            return;
        }

        // Handle Update requests
        if ($requestType === 'Update') {
            $this->handleUpdateRequest($roleArn, $uniqueId, $externalId, $responseUrl, $stackId, $requestId, $logicalResourceId, $physicalResourceId);
            $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
            return;
        }

        // Unknown request type
        $this->warn("Unknown RequestType: {$requestType}, deleting from queue");
        $this->deleteMessage($sqsClient, $queueUrl, $receiptHandle);
    }

    /**
     * Handle Delete request from CloudFormation
     */
    private function handleDeleteRequest(?string $uniqueId, ?string $externalId, string $responseUrl, string $stackId, string $requestId, ?string $logicalResourceId, ?string $physicalResourceId): void
    {
        if (!$uniqueId || !$externalId) {
            $this->warn('Delete request missing unique_id or external_id');
            $response = $this->sendCloudFormationResponse($responseUrl, 'SUCCESS', 'Delete request processed (no account found)', $stackId, $requestId, $logicalResourceId, $physicalResourceId);
            
            if (app()->environment(['local', 'dev'])) {
                Log::info('AWS account deleted via SQS message (dev verbose)', [
                    'unique_id' => $uniqueId,
                    'external_id' => $externalId,
                    'response_url' => $responseUrl,
                    'response' => $response,
                ]);
            }
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
        // Use externalId as PhysicalResourceId if not provided (should be set from Create response)
        $responsePhysicalResourceId = $physicalResourceId ?? $externalId;
        $response = $this->sendCloudFormationResponse($responseUrl, 'SUCCESS', 'Delete request processed', $stackId, $requestId, $logicalResourceId, $responsePhysicalResourceId);
        
        if (app()->environment(['local', 'dev'])) {
            $logData = [
                'unique_id' => $uniqueId,
                'external_id' => $externalId,
                'response_url' => $responseUrl,
                'response' => $response,
            ];
            
            if ($account) {
                $logData['account_id'] = $account->id;
            }
            
            Log::info('AWS account deleted via SQS message (dev verbose)', $logData);
        }
    }

    /**
     * Handle Create request from CloudFormation
     */
    private function handleCreateRequest(?string $roleArn, ?string $uniqueId, ?string $externalId, string $responseUrl, string $stackId, string $requestId, ?string $logicalResourceId, ?string $physicalResourceId): void
    {
        if (!$roleArn || !$externalId || !$uniqueId) {
            $this->warn('Create message missing required fields (TopsRoleArn, TopsExternalId, TopsUniqueId)');
            $this->recordRejection('missing_required_fields', [
                'has_role_arn' => !empty($roleArn),
                'has_unique_id' => !empty($uniqueId),
                'has_external_id' => !empty($externalId),
            ]);
            $response = $this->sendCloudFormationResponse($responseUrl, 'FAILED', 'Missing required fields in ResourceProperties', $stackId, $requestId, $logicalResourceId, $physicalResourceId);

            if (app()->environment(['local', 'dev'])) {
                Log::info('Create request rejected (missing required fields)', [
                    'unique_id' => $uniqueId,
                    'external_id' => $externalId,
                    'role_arn' => $roleArn,
                    'response_url' => $responseUrl,
                    'response' => $response,
                ]);
            }
            return;
        }

        $awsAccountId = $this->extractAwsAccountIdFromRoleArn($roleArn);

        if (!$awsAccountId) {
            $this->error("Could not extract AWS Account ID from Role ARN: {$roleArn}");
            $this->recordRejection('invalid_role_arn', [
                'unique_id' => $uniqueId,
                'role_arn' => $roleArn,
            ]);
            $response = $this->sendCloudFormationResponse($responseUrl, 'FAILED', "Invalid Role ARN format: {$roleArn}", $stackId, $requestId, $logicalResourceId, $physicalResourceId);

            if (app()->environment('local')) {
                Log::info('AWS account activated via SQS message (dev verbose)', [
                    'unique_id' => $uniqueId,
                    'external_id' => $externalId,
                    'role_arn' => $roleArn,
                    'request_type' => 'Create',
                    'response_url' => $responseUrl,
                    'response' => $response,
                ]);
            }
            return;
        }

        // N-11 gap 1: the message names the child account twice — once in StackId,
        // which CloudFormation itself sets, and once inside TopsRoleArn, which is
        // whatever the publisher chose to write. Only the first is authoritative.
        // Without comparing them, a role ARN pointing at an account the stack-runner
        // does not own is accepted and scanned.
        $stackAccountId = $this->extractAwsAccountIdFromStackId($stackId);

        if (!$stackAccountId || !hash_equals($stackAccountId, $awsAccountId)) {
            $this->error("Role ARN account {$awsAccountId} does not match stack account " . ($stackAccountId ?? 'unknown'));
            $this->recordRejection('stack_account_mismatch', [
                'unique_id' => $uniqueId,
                'role_arn_account_id' => $awsAccountId,
                'stack_account_id' => $stackAccountId,
                'stack_id' => $stackId,
            ]);
            $response = $this->sendCloudFormationResponse($responseUrl, 'FAILED', 'Role ARN account does not match the account that created the stack', $stackId, $requestId, $logicalResourceId, $physicalResourceId);

            if (app()->environment(['local', 'dev'])) {
                Log::info('Create request rejected (stack account mismatch, dev verbose)', [
                    'unique_id' => $uniqueId,
                    'external_id' => $externalId,
                    'role_arn' => $roleArn,
                    'response_url' => $responseUrl,
                    'response' => $response,
                ]);
            }
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
                'request_type' => 'Create',
            ]);
            $this->recordRejection('account_not_found', [
                'unique_id' => $uniqueId,
            ]);
            $this->warn("Account not found for unique_id: {$uniqueId}, external_id: {$externalId}");
            // Send FAILED response to CloudFormation
            $response = $this->sendCloudFormationResponse($responseUrl, 'FAILED', "Account not found for unique_id: {$uniqueId}", $stackId, $requestId, $logicalResourceId, $physicalResourceId);

            if (app()->environment(['local', 'dev'])) {
                Log::info('AWS account activated via SQS message (dev verbose)', [
                    'unique_id' => $uniqueId,
                    'external_id' => $externalId,
                    'role_arn' => $roleArn,
                    'request_type' => 'Create',
                    'response_url' => $responseUrl,
                    'response' => $response,
                ]);
            }
            return;
        }

        // N-11 gap 2: only a record still waiting to be linked may be linked. Without
        // this, a replayed or forged Create repoints an account that is already live
        // at a different role — the credentials the scanner uses are swapped under it.
        if ($account->status !== 'pending') {
            $this->warn("Account {$account->id} is not pending (status: {$account->status}), refusing to relink");
            $this->recordRejection('account_not_pending', [
                'account_id' => $account->id,
                'unique_id' => $uniqueId,
                'status' => $account->status,
            ]);
            $this->sendCloudFormationResponse($responseUrl, 'FAILED', 'Account is not awaiting linking', $stackId, $requestId, $logicalResourceId, $physicalResourceId);
            return;
        }

        // N-11 gap 3: a pending record stays linkable forever, so an onboarding link
        // that was created and abandoned months ago is still a live target. Bound it.
        //
        // Measured from updated_at, not created_at: the controller reuses one pending
        // row per organization and touches it each time it hands out a link, so
        // updated_at is when the link the user is holding was actually issued.
        // Using created_at would expire links the user had only just been given.
        $windowHours = (int) config('services.aws.account_link_window_hours');

        if ($windowHours > 0 && $account->updated_at->addHours($windowHours)->isPast()) {
            $this->warn("Account {$account->id} pending link window expired");
            $this->recordRejection('link_window_expired', [
                'account_id' => $account->id,
                'unique_id' => $uniqueId,
                'link_issued_at' => $account->updated_at->toIso8601String(),
                'window_hours' => $windowHours,
            ]);
            $this->sendCloudFormationResponse($responseUrl, 'FAILED', "Linking window of {$windowHours}h has expired — start the connection again from TOPS", $stackId, $requestId, $logicalResourceId, $physicalResourceId);
            return;
        }

        // Check for soft-deleted accounts with the same organization_id and aws_account_id
        // This can happen if an account was deleted but the AWS account number is still present
        // We need to permanently delete them to avoid unique constraint violations
        $softDeletedAccounts = AwsAccount::onlyTrashed()
            ->where('organization_id', $account->organization_id)
            ->where('aws_account_id', $awsAccountId)
            ->get();

        if ($softDeletedAccounts->isNotEmpty()) {
            foreach ($softDeletedAccounts as $softDeletedAccount) {
                $this->info("Permanently deleting soft-deleted account {$softDeletedAccount->id} to resolve duplicate constraint");
                Log::info('Permanently deleting soft-deleted AWS account to resolve duplicate', [
                    'deleted_account_id' => $softDeletedAccount->id,
                    'organization_id' => $account->organization_id,
                    'aws_account_id' => $awsAccountId,
                    'active_account_id' => $account->id,
                ]);
                $softDeletedAccount->forceDelete();
            }
        }

        // Update account
        // Use fill() and save() to ensure the mutator is called correctly
        $account->fill([
            'iam_role_arn' => $roleArn,
            'aws_account_id' => $awsAccountId,
            'name' => $account->name === 'Pending AWS Account' 
                ? "AWS Account {$awsAccountId}" 
                : $account->name,
            'status' => 'completed',
        ]);
        $account->save();

        $this->info("AWS account {$account->id} updated to completed status");

        Log::info('AWS account activated via SQS message', [
            'account_id' => $account->id,
            'aws_account_id' => $awsAccountId,
            'unique_id' => $uniqueId,
            'external_id' => $externalId,
            'request_type' => 'Create',
        ]);

        // Send SUCCESS response to CloudFormation
        // For Create requests, PhysicalResourceId should be the externalId
        $responsePhysicalResourceId = $physicalResourceId ?? $externalId;
        $response = $this->sendCloudFormationResponse($responseUrl, 'SUCCESS', 'Successfully processed the request', $stackId, $requestId, $logicalResourceId, $responsePhysicalResourceId);
        
        if (app()->environment(['local', 'dev'])) {
            Log::info('AWS account activated via SQS message (dev verbose)', [
                'account_id' => $account->id,
                'aws_account_id' => $awsAccountId,
                'unique_id' => $uniqueId,
                'external_id' => $externalId,
                'request_type' => 'Create',
                'response_url' => $responseUrl,
                'response' => $response,
            ]);
        }
    }

    /**
     * Handle Update request from CloudFormation
     */
    private function handleUpdateRequest(?string $roleArn, ?string $uniqueId, ?string $externalId, string $responseUrl, string $stackId, string $requestId, ?string $logicalResourceId, ?string $physicalResourceId): void
    {
        // If we have the required fields, try to update the account
        if ($roleArn && $uniqueId && $externalId) {
            // Find account by unique_id and external_id
            $account = AwsAccount::where('unique_id', $uniqueId)
                ->where('external_id', $externalId)
                ->first();

            if ($account) {
                // Check if the IAM Role ARN has changed
                $currentRoleArn = $account->iam_role_arn;
                
                if ($currentRoleArn !== $roleArn) {
                    $awsAccountId = $this->extractAwsAccountIdFromRoleArn($roleArn);
                    $stackAccountId = $this->extractAwsAccountIdFromStackId($stackId);

                    if (!$awsAccountId) {
                        $this->warn("Could not extract AWS Account ID from Role ARN: {$roleArn}");
                        $this->recordRejection('invalid_role_arn', [
                            'account_id' => $account->id,
                            'unique_id' => $uniqueId,
                            'role_arn' => $roleArn,
                            'request_type' => 'Update',
                        ]);
                    } elseif (!$stackAccountId || !hash_equals($stackAccountId, $awsAccountId)) {
                        // Update repoints a live account at a new role, so it needs the
                        // same StackId cross-check as Create — otherwise closing the gap
                        // on Create only moves the forged-ARN path one RequestType over.
                        $this->warn("Update rejected: role ARN account {$awsAccountId} does not match stack account " . ($stackAccountId ?? 'unknown'));
                        $this->recordRejection('stack_account_mismatch', [
                            'account_id' => $account->id,
                            'unique_id' => $uniqueId,
                            'role_arn_account_id' => $awsAccountId,
                            'stack_account_id' => $stackAccountId,
                            'request_type' => 'Update',
                        ]);
                    } else {
                        // Update the account with new IAM Role ARN and AWS Account ID
                        $account->fill([
                            'iam_role_arn' => $roleArn,
                            'aws_account_id' => $awsAccountId,
                        ]);
                        $account->save();

                        $this->info("AWS account {$account->id} updated with new IAM Role ARN");
                        Log::info('AWS account IAM Role ARN updated via SQS message', [
                            'account_id' => $account->id,
                            'aws_account_id' => $awsAccountId,
                            'unique_id' => $uniqueId,
                            'external_id' => $externalId,
                            'request_type' => 'Update',
                        ]);
                    }
                } else {
                    $this->info("IAM Role ARN unchanged for account {$account->id}");
                    Log::info('AWS account Update request: IAM Role ARN unchanged', [
                        'account_id' => $account->id,
                        'unique_id' => $uniqueId,
                        'external_id' => $externalId,
                    ]);
                }
            } else {
                $this->warn("Account not found for Update request: unique_id={$uniqueId}, external_id={$externalId}");
                Log::warning('Update request: Account not found', [
                    'unique_id' => $uniqueId,
                    'external_id' => $externalId,
                ]);
            }
        } else {
            $this->warn('Update request missing required fields (TopsRoleArn, TopsExternalId, TopsUniqueId)');
            Log::warning('Update request missing required fields', [
                'has_role_arn' => !empty($roleArn),
                'has_unique_id' => !empty($uniqueId),
                'has_external_id' => !empty($externalId),
            ]);
        }

        // Always send SUCCESS response for Update requests
        // Use PhysicalResourceId from the request if available
        $responsePhysicalResourceId = $physicalResourceId;
        $response = $this->sendCloudFormationResponse($responseUrl, 'SUCCESS', 'Update request processed', $stackId, $requestId, $logicalResourceId, $responsePhysicalResourceId);
        
        $this->info("Update request processed successfully");
        Log::info('CloudFormation Update request processed', [
            'request_id' => $requestId,
            'stack_id' => $stackId,
            'logical_resource_id' => $logicalResourceId,
            'physical_resource_id' => $physicalResourceId,
        ]);
        
        if (app()->environment(['local', 'dev'])) {
            Log::info('AWS account updated via SQS message (dev verbose)', [
                'request_type' => 'Update',
                'role_arn' => $roleArn,
                'unique_id' => $uniqueId,
                'external_id' => $externalId,
                'response_url' => $responseUrl,
                'response' => $response,
            ]);
        }
    }

    /**
     * Send response to CloudFormation custom resource ResponseURL
     * 
     * @return array The response data that was sent
     */
    private function sendCloudFormationResponse(string $responseUrl, string $status, string $reason, string $stackId, string $requestId, ?string $logicalResourceId, ?string $physicalResourceId): array
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
            
            return $response;
        } catch (\Exception $e) {
            $this->error("Failed to send CloudFormation response: {$e->getMessage()}");
            Log::error('Failed to send CloudFormation response', [
                'error' => $e->getMessage(),
                'response_url' => $responseUrl,
                'status' => $status,
            ]);
            
            // Return the response array even if sending failed, for logging purposes
            return [
                'Status' => $status,
                'Reason' => $reason,
                'StackId' => $stackId,
                'RequestId' => $requestId,
                'LogicalResourceId' => $logicalResourceId,
                'PhysicalResourceId' => $physicalResourceId,
            ];
        }
    }

    /**
     * Extract AWS Account ID from IAM Role ARN.
     * Format: arn:aws:iam::123456789012:role/RoleName
     */
    private function extractAwsAccountIdFromRoleArn(string $roleArn): ?string
    {
        if (preg_match('/arn:aws:iam::(\d+):role\/(.+)/', $roleArn, $matches) === 1) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Pull the account that owns the CloudFormation stack out of its StackId.
     *
     * StackId is set by CloudFormation, not by the template, so this is the one
     * account identifier in the message the publisher did not choose. Everything
     * under ResourceProperties is caller-supplied and has to agree with it.
     *
     * Shape: arn:aws:cloudformation:<region>:<account>:stack/<name>/<guid>
     * The partition varies (aws, aws-cn, aws-us-gov), so it is matched loosely.
     */
    private function extractAwsAccountIdFromStackId(string $stackId): ?string
    {
        if (preg_match('#^arn:[a-z0-9-]+:cloudformation:[a-z0-9-]+:(\d{12}):stack/#', $stackId, $matches) === 1) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Count a rejected account-linking message under a stable, readable key.
     *
     * A `Log::warning` alone is not something an operator can alarm on without a
     * log pipeline, and a self-hosted install may have none. These counters live in
     * the cache store (`database` by default, so they survive a restart) and are
     * read back with `php artisan aws:link-rejections`.
     *
     * Counting is best-effort by design: failing to record a metric must never stop
     * a message being rejected, which is the part that actually matters.
     */
    private function recordRejection(string $reason, array $context = []): void
    {
        Log::warning('Account-linking message rejected', [
            'event' => self::REJECTION_EVENT,
            'reason' => $reason,
        ] + $context);

        try {
            foreach ([self::REJECTION_CACHE_PREFIX . 'total', self::REJECTION_CACHE_PREFIX . $reason] as $key) {
                // The database cache store cannot increment a key that does not
                // exist yet, so seed it first. add() is a no-op once it does.
                Cache::add($key, 0, self::REJECTION_CACHE_TTL);
                Cache::increment($key);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not record link-rejection counter', [
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete message from SQS queue.
     * Rethrows AwsException so callers can decide whether to retry (message remains visible).
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
                'queue_url' => $queueUrl,
            ]);
            throw $e;
        }
    }
}
