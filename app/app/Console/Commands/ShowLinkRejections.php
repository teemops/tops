<?php

namespace App\Console\Commands;

use App\Console\Commands\ProcessSqsMessages;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Read back the account-linking rejection counters that aws:process-sqs writes.
 *
 * N-11 asked for a signal an operator can alarm on, rather than only a
 * `Log::warning` that a self-hosted install may have no pipeline to collect. The
 * counters are meaningless if nothing can read them, so this is that half:
 *
 *     php artisan aws:link-rejections
 *     php artisan aws:link-rejections --json
 *
 * A non-zero `stack_account_mismatch` or `signature_verification_failed` means
 * something published to the SNS topic that did not come from a legitimate
 * onboarding run — worth looking at. A non-zero `account_not_found` on its own is
 * usually benign: a stack deleted and re-run, or an abandoned onboarding link.
 *
 * The counters only see messages that actually reached the poller. A ping carrying
 * a wrong install id is filtered out at the SNS topic and never gets that far, so
 * the quarantine queue depth is reported alongside them — otherwise this command
 * would answer "nothing rejected" while onboarding was silently failing.
 *
 * Read these as rejected *deliveries*, not distinct messages. Every reason except
 * `signature_verification_failed` deletes the message, so those count one-for-one;
 * a message that fails signature verification is deliberately left on the queue for
 * redelivery, so one such message counts up to maxReceiveCount (5) times before it
 * lands in the DLQ.
 */
class ShowLinkRejections extends Command
{
    protected $signature = 'aws:link-rejections
        {--json : Emit JSON, for a monitoring agent rather than a human}
        {--reset : Clear the counters after reading them}';

    protected $description = 'Show why account-linking messages were rejected (N-11 counters)';

    /**
     * The reasons aws:process-sqs can record. Listed explicitly so a reason that
     * has never fired still prints as 0 — "no such key" and "never happened" look
     * identical in a cache store, and only one of them is reassuring.
     */
    private const REASONS = [
        'not_an_sns_notification',
        'signature_verification_failed',
        'missing_required_fields',
        'invalid_role_arn',
        'stack_account_mismatch',
        'account_not_found',
        'account_not_pending',
        'link_window_expired',
    ];

    public function handle(): int
    {
        $prefix = ProcessSqsMessages::REJECTION_CACHE_PREFIX;

        $counts = [];
        foreach (self::REASONS as $reason) {
            $counts[$reason] = (int) Cache::get($prefix . $reason, 0);
        }
        $total = (int) Cache::get($prefix . 'total', 0);
        $quarantine = $this->quarantineDepth();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'total' => $total,
                'reasons' => $counts,
                'quarantined' => $quarantine['count'],
                'quarantine_status' => $quarantine['status'],
            ], JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['Reason', 'Count'],
                collect($counts)
                    ->map(fn (int $count, string $reason) => [$reason, $count])
                    ->values()
                    ->push(['TOTAL', $total])
                    ->push(['quarantined (filtered at the topic)', $quarantine['count'] ?? $quarantine['status']])
                    ->all()
            );

            // Saying "nothing was rejected" while messages sit in quarantine would be
            // the same silent failure this whole feature exists to prevent — the two
            // numbers come from different layers and only one of them is in the cache.
            //
            // A null count means the queue could not be read, which is not the same as
            // zero. The all-clear is only given when both halves are positively known
            // to be empty; otherwise the warning below says what could not be checked.
            if ($total === 0 && $quarantine['count'] === 0) {
                $this->info('No account-linking messages have been rejected.');
            }

            if (($quarantine['count'] ?? 0) > 0) {
                $this->warn(sprintf(
                    "%d message(s) were filtered out at the SNS topic and never reached the poller.\n"
                    . "That is a wrong or missing TopsInstallId — a stale onboarding link, a reinstall,\n"
                    . "or someone publishing to the topic. Inspect one with:\n"
                    . "  aws sqs receive-message --queue-url \$(aws sqs get-queue-url --queue-name %s --query QueueUrl --output text) --visibility-timeout 0",
                    $quarantine['count'],
                    config('services.aws.quarantine_sqs_name') ?: 'teemops_quarantine',
                ));
            }

            if ($quarantine['count'] === null) {
                $this->warn('Quarantine depth unavailable: ' . $quarantine['status']);
            }
        }

        if ($this->option('reset')) {
            // Only the cache counters. The quarantine queue is left alone on purpose:
            // clearing it would discard the messages themselves, which are the only
            // evidence of what was filtered and why.
            foreach ([...self::REASONS, 'total'] as $key) {
                Cache::forget($prefix . $key);
            }
            $this->info('Counters reset.');
        }

        return Command::SUCCESS;
    }

    /**
     * How many rejected pings are sitting in the quarantine queue.
     *
     * These never reach `aws:process-sqs`, so they are invisible to the cache
     * counters above — which is exactly why reporting them here matters. A run of
     * this command that says "nothing rejected" while the queue is filling up would
     * be worse than no command at all.
     *
     * Degrades to a status string rather than failing: an install that never ran the
     * AWS step, or a container without credentials, should still get its counters.
     *
     * @return array{count: int|null, status: string}
     */
    private function quarantineDepth(): array
    {
        $queueName = config('services.aws.quarantine_sqs_name');

        if (!$queueName) {
            return ['count' => null, 'status' => 'not configured (run ./install.sh --aws-only)'];
        }

        try {
            // Resolved from the container when something has bound one, so tests can
            // substitute a client instead of reaching AWS. ProcessSqsMessages builds
            // its client inline and its tests skip that path as a result; this is the
            // same client with a seam, because both branches here need covering.
            $sqs = app()->bound(SqsClient::class) ? app(SqsClient::class) : new SqsClient([
                'version' => 'latest',
                'region' => config('services.aws.deployment_region', config('services.aws.region', 'us-east-1')),
                'http' => ['connect_timeout' => 5, 'timeout' => 10],
            ]);

            $url = $sqs->getQueueUrl(['QueueName' => $queueName])->get('QueueUrl');

            $attributes = $sqs->getQueueAttributes([
                'QueueUrl' => $url,
                'AttributeNames' => ['ApproximateNumberOfMessages'],
            ])->get('Attributes');

            return [
                'count' => (int) ($attributes['ApproximateNumberOfMessages'] ?? 0),
                'status' => 'ok',
            ];
        } catch (\Throwable $e) {
            return ['count' => null, 'status' => $e->getMessage()];
        }
    }
}
