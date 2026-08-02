<?php

namespace App\Console\Commands;

use App\Console\Commands\ProcessSqsMessages;
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

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'total' => $total,
                'reasons' => $counts,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->table(
                ['Reason', 'Count'],
                collect($counts)
                    ->map(fn (int $count, string $reason) => [$reason, $count])
                    ->values()
                    ->push(['TOTAL', $total])
                    ->all()
            );

            if ($total === 0) {
                $this->info('No account-linking messages have been rejected.');
            }
        }

        if ($this->option('reset')) {
            foreach ([...self::REASONS, 'total'] as $key) {
                Cache::forget($prefix . $key);
            }
            $this->info('Counters reset.');
        }

        return Command::SUCCESS;
    }
}
