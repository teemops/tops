<?php

namespace App\Console\Commands;

use App\Models\AwsAccount;
use App\Models\Organization;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Inject a synthetic CloudFormation "TopsCustomNotifier" callback into SNS or SQS,
 * so the account-linking worker (aws:process-sqs) can be tested without launching a
 * real CloudFormation stack (which takes 10-15 min to fail/roll back).
 *
 * The message mirrors exactly what CloudFormation publishes for the child-account
 * Custom::TeemopsPingSNS resource, and — for the SQS target — is wrapped in the same
 * SNS "Notification" envelope the worker receives in production.
 */
class TestSqsCallback extends Command
{
    protected $signature = 'aws:test-callback
        {--target=sns : Where to inject the message: "sns" (tests topic->queue->worker), "sqs" (worker only), or "dlq" (seeds the dead-letter queue to exercise aws:redrive-dlq)}
        {--request-type=Create : CloudFormation request type: Create, Update, or Delete}
        {--org= : Existing organization org_id to target (used as TopsUniqueId). Defaults to the latest org when --create-account is set}
        {--external-id= : TopsExternalId to match an existing pending account; generated when --create-account is set}
        {--role-arn=arn:aws:iam::111122223333:role/TeemOps : Fake child-account TeemOps role ARN}
        {--response-url= : CloudFormation ResponseURL (a harmless placeholder is used if omitted)}
        {--create-account : Seed/reuse a matching pending AWS account first (happy-path test)}
        {--wait=30 : Seconds to poll the DB for the expected result after sending (0 to skip)}';

    protected $description = 'Send a fake CloudFormation CustomNotifier callback to SNS/SQS to test the account-linking worker';

    public function handle(): int
    {
        $target = strtolower((string) $this->option('target'));
        if (! in_array($target, ['sns', 'sqs', 'dlq'], true)) {
            $this->error('--target must be "sns", "sqs", or "dlq".');
            return self::FAILURE;
        }

        $requestType = ucfirst(strtolower((string) $this->option('request-type')));
        if (! in_array($requestType, ['Create', 'Update', 'Delete'], true)) {
            $this->error('--request-type must be Create, Update, or Delete.');
            return self::FAILURE;
        }

        $region = config('services.aws.region');
        if (! $region) {
            $this->error('AWS region not configured (TOPS_DEPLOYMENT_REGION). Run ./install.sh first.');
            return self::FAILURE;
        }

        // Resolve the identifiers the worker will match on (unique_id + external_id).
        [$uniqueId, $externalId, $account] = $this->resolveIdentifiers();
        if ($uniqueId === null) {
            return self::FAILURE; // resolveIdentifiers already printed the reason
        }

        $roleArn = (string) $this->option('role-arn');
        $topicArn = $this->topicArn($region);

        $cfnRequest = $this->buildCloudFormationRequest($requestType, $topicArn, $roleArn, $uniqueId, $externalId, $region);

        $this->line("Target:      <info>{$target}</info>");
        $this->line("RequestType: <info>{$requestType}</info>");
        $this->line("UniqueId:    <info>{$uniqueId}</info>");
        $this->line("ExternalId:  <info>{$externalId}</info>");
        $this->line('RoleArn:     <info>'.$roleArn.'</info>');

        try {
            if ($target === 'sns') {
                $this->publishToSns($topicArn, $cfnRequest, $region);
                $this->info("Published to SNS topic: {$topicArn}");
            } else {
                $queueName = $target === 'dlq'
                    ? config('services.aws.sqs_dlq_name')
                    : config('services.aws.sqs_name');
                $queueUrl = $this->sendToSqs($cfnRequest, $topicArn, $region, $queueName);
                $this->info("Sent to SQS queue: {$queueUrl}");
            }
        } catch (\Throwable $e) {
            $this->error('Failed to send message: '.$e->getMessage());
            return self::FAILURE;
        }

        $wait = (int) $this->option('wait');

        // Nothing polls the DLQ, so there is no worker result to wait for — the
        // message sits there until aws:redrive-dlq puts it back on teemops_main.
        if ($target === 'dlq') {
            $this->comment('Seeded the DLQ. Inspect with: php artisan aws:redrive-dlq --peek');
            return self::SUCCESS;
        }

        if ($wait > 0 && $account !== null) {
            return $this->waitForResult($uniqueId, $externalId, $requestType, $wait);
        }

        if ($wait > 0) {
            $this->comment('No matching account to observe (pass --create-account or --org + --external-id to assert). Watch the worker logs: docker compose logs -f worker');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?AwsAccount}
     */
    private function resolveIdentifiers(): array
    {
        if ($this->option('create-account')) {
            $orgId = $this->option('org');
            $organization = $orgId
                ? Organization::where('org_id', $orgId)->first()
                : Organization::latest()->first();

            if (! $organization) {
                $this->error($orgId
                    ? "Organization not found for org_id: {$orgId}"
                    : 'No organizations exist yet. Create one in the app, or pass --org=<org_id>.');
                return [null, null, null];
            }

            // Mirror AwsAccountsController::init() — reuse an existing pending row.
            $account = AwsAccount::where('organization_id', $organization->id)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->first();

            if (! $account) {
                $account = AwsAccount::create([
                    'organization_id' => $organization->id,
                    'name' => 'Pending AWS Account',
                    'status' => 'pending',
                    'unique_id' => $organization->org_id,
                    'external_id' => $this->option('external-id') ?: null, // null -> auto-generated
                ]);
                $this->comment("Created pending AWS account {$account->id} under org {$organization->org_id}");
            } else {
                $this->comment("Reusing pending AWS account {$account->id} under org {$organization->org_id}");
            }

            return [$account->unique_id, $account->external_id, $account];
        }

        // No account seeding: caller may still target an existing pending account by
        // passing --org (unique_id) and --external-id, otherwise use random ids
        // (the worker will log "Account not found", which still proves it read SQS).
        $uniqueId = $this->option('org') ?: (string) Str::uuid();
        $externalId = $this->option('external-id') ?: (string) Str::uuid();

        $account = AwsAccount::where('unique_id', $uniqueId)
            ->where('external_id', $externalId)
            ->first();

        return [$uniqueId, $externalId, $account];
    }

    private function topicArn(string $region): string
    {
        $configured = config('services.aws.sns_arn');
        if ($configured) {
            return $configured;
        }

        $accountId = config('services.aws.parent_account_id');
        return sprintf('arn:aws:sns:%s:%s:teemops-sns', $region, $accountId ?: '000000000000');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCloudFormationRequest(string $requestType, string $topicArn, string $roleArn, string $uniqueId, string $externalId, string $region): array
    {
        preg_match('/arn:aws:iam::(\d+):role\//', $roleArn, $m);
        $childAccountId = $m[1] ?? '111122223333';

        return [
            'RequestType' => $requestType,
            'ServiceToken' => $topicArn,
            'ResponseURL' => $this->option('response-url') ?: 'https://example.com/teemops-test-callback',
            'StackId' => sprintf('arn:aws:cloudformation:%s:%s:stack/tops-vendor-audit-test/%s', $region, $childAccountId, Str::uuid()),
            'RequestId' => (string) Str::uuid(),
            'LogicalResourceId' => 'TopsCustomNotifier',
            'ResourceType' => 'Custom::TeemopsPingSNS',
            'ResourceProperties' => [
                'ServiceToken' => $topicArn,
                'TopsRoleArn' => $roleArn,
                'TopsExternalId' => $externalId,
                'TopsUniqueId' => $uniqueId,
                'TopsType' => 'ops',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $cfnRequest
     */
    private function publishToSns(string $topicArn, array $cfnRequest, string $region): void
    {
        $client = new SnsClient(['version' => 'latest', 'region' => $region]);
        $client->publish([
            'TopicArn' => $topicArn,
            'Message' => json_encode($cfnRequest),
        ]);
    }

    /**
     * @param array<string, mixed> $cfnRequest
     */
    private function sendToSqs(array $cfnRequest, string $topicArn, string $region, ?string $sqsName): string
    {
        if (! $sqsName) {
            throw new \RuntimeException('Queue name not configured (TOPS_SQS_NAME / TOPS_SQS_DLQ_NAME). Run ./install.sh first.');
        }

        $client = new SqsClient(['version' => 'latest', 'region' => $region]);
        $queueUrl = $client->getQueueUrl(['QueueName' => $sqsName])->get('QueueUrl');

        // Wrap in the same SNS "Notification" envelope the worker sees from SNS -> SQS.
        $envelope = [
            'Type' => 'Notification',
            'MessageId' => (string) Str::uuid(),
            'TopicArn' => $topicArn,
            'Message' => json_encode($cfnRequest),
            'Timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
        ];

        $client->sendMessage([
            'QueueUrl' => $queueUrl,
            'MessageBody' => json_encode($envelope),
        ]);

        return $queueUrl;
    }

    private function waitForResult(string $uniqueId, string $externalId, string $requestType, int $seconds): int
    {
        $this->line("Waiting up to {$seconds}s for the worker to process the message...");

        $deadline = microtime(true) + $seconds;
        do {
            $account = AwsAccount::withTrashed()
                ->where('unique_id', $uniqueId)
                ->where('external_id', $externalId)
                ->first();

            if ($requestType === 'Delete') {
                if ($account === null || $account->trashed()) {
                    $this->info('PASS — account was removed by the worker (Delete processed).');
                    return self::SUCCESS;
                }
            } elseif ($account && $account->status === 'completed') {
                $this->info("PASS — worker activated account {$account->id} (aws_account_id={$account->aws_account_id}).");
                return self::SUCCESS;
            }

            usleep(2_000_000);
        } while (microtime(true) < $deadline);

        $this->error('FAIL — expected result not observed within timeout. Check: docker compose logs worker');
        return self::FAILURE;
    }
}
