<?php

namespace Tests\Unit;

use App\Console\Commands\ProcessSqsMessages;
use Aws\Result;
use Aws\Sqs\SqsClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * N-11. This command is the readout an operator is told to run, so what matters is
 * that it never gives a falsely reassuring answer.
 *
 * The quarantine queue is the case that motivated it, and it was found on a live AWS
 * run rather than here: a ping carrying a wrong install id is filtered out at the SNS
 * topic and never reaches the poller, so it cannot appear in the cache counters. The
 * command reported "No account-linking messages have been rejected" while a rejected
 * message sat in quarantine.
 */
class ShowLinkRejectionsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function seedCounter(string $reason, int $value): void
    {
        Cache::put(ProcessSqsMessages::REJECTION_CACHE_PREFIX . $reason, $value);
    }

    /**
     * Bind a quarantine queue holding $depth messages.
     */
    private function fakeQuarantine(int $depth): void
    {
        Config::set('services.aws.quarantine_sqs_name', 'teemops_quarantine');

        $sqs = Mockery::mock(SqsClient::class);
        $sqs->shouldReceive('getQueueUrl')->andReturn(new Result([
            'QueueUrl' => 'https://sqs.us-west-2.amazonaws.com/123456789012/teemops_quarantine',
        ]));
        $sqs->shouldReceive('getQueueAttributes')->andReturn(new Result([
            'Attributes' => ['ApproximateNumberOfMessages' => (string) $depth],
        ]));

        $this->app->instance(SqsClient::class, $sqs);
    }

    private function runJson(): array
    {
        Artisan::call('aws:link-rejections', ['--json' => true]);

        return json_decode(Artisan::output(), true);
    }

    public function test_it_reports_each_reason_and_the_total(): void
    {
        $this->fakeQuarantine(0);
        $this->seedCounter('stack_account_mismatch', 3);
        $this->seedCounter('total', 3);

        $json = $this->runJson();

        $this->assertSame(3, $json['total']);
        $this->assertSame(3, $json['reasons']['stack_account_mismatch']);
    }

    /**
     * A reason that has never fired must still be reported as 0 — "no such cache key"
     * and "never happened" are indistinguishable otherwise, and only one is reassuring.
     */
    public function test_reasons_that_never_fired_are_still_listed(): void
    {
        $this->fakeQuarantine(0);

        $json = $this->runJson();

        $this->assertSame(0, $json['reasons']['link_window_expired']);
        $this->assertSame(0, $json['reasons']['signature_verification_failed']);
    }

    public function test_it_says_nothing_was_rejected_when_both_layers_are_empty(): void
    {
        $this->fakeQuarantine(0);

        $this->artisan('aws:link-rejections')
            ->expectsOutputToContain('No account-linking messages have been rejected.')
            ->assertSuccessful();
    }

    public function test_it_does_not_claim_all_clear_when_counters_are_non_zero(): void
    {
        $this->fakeQuarantine(0);
        $this->seedCounter('account_not_pending', 1);
        $this->seedCounter('total', 1);

        $this->artisan('aws:link-rejections')
            ->doesntExpectOutputToContain('No account-linking messages have been rejected.')
            ->assertSuccessful();
    }

    /**
     * The regression this fix exists for. Counters are all zero because the message
     * never reached the poller — but it was still rejected, and saying otherwise sent
     * an operator looking in the wrong place.
     */
    public function test_it_does_not_claim_all_clear_when_messages_sit_in_quarantine(): void
    {
        $this->fakeQuarantine(1);

        $this->artisan('aws:link-rejections')
            ->doesntExpectOutputToContain('No account-linking messages have been rejected.')
            ->expectsOutputToContain('filtered out at the SNS topic')
            ->assertSuccessful();
    }

    public function test_quarantine_depth_is_reported_in_json(): void
    {
        $this->fakeQuarantine(4);

        $json = $this->runJson();

        $this->assertSame(4, $json['quarantined']);
        $this->assertSame('ok', $json['quarantine_status']);
    }

    /**
     * An install that never ran the AWS step still gets its counters, and is told the
     * quarantine could not be read rather than being given a silent all-clear.
     */
    public function test_an_unconfigured_quarantine_queue_degrades_without_failing(): void
    {
        Config::set('services.aws.quarantine_sqs_name', null);
        $this->seedCounter('total', 2);

        $json = $this->runJson();

        $this->assertSame(2, $json['total']);
        $this->assertNull($json['quarantined']);
        $this->assertStringContainsString('not configured', $json['quarantine_status']);
    }

    /**
     * A queue that cannot be reached must not take the whole command down with it —
     * the counters are still worth reporting, and "unknown" is reported as unknown.
     */
    public function test_an_unreachable_quarantine_queue_degrades_without_failing(): void
    {
        Config::set('services.aws.quarantine_sqs_name', 'teemops_quarantine');

        $sqs = Mockery::mock(SqsClient::class);
        $sqs->shouldReceive('getQueueUrl')->andThrow(new \RuntimeException('no credentials'));
        $this->app->instance(SqsClient::class, $sqs);

        $json = $this->runJson();

        $this->assertNull($json['quarantined']);
        $this->assertStringContainsString('no credentials', $json['quarantine_status']);
    }

    /**
     * Reset clears the counters. It deliberately does not touch the quarantine queue,
     * whose messages are the only evidence of what was filtered and why.
     */
    public function test_reset_clears_the_counters_but_not_the_queue(): void
    {
        $this->fakeQuarantine(2);
        $this->seedCounter('total', 5);
        $this->seedCounter('stack_account_mismatch', 5);

        $this->artisan('aws:link-rejections', ['--reset' => true])->assertSuccessful();

        $this->assertSame(0, (int) Cache::get(ProcessSqsMessages::REJECTION_CACHE_PREFIX . 'total', 0));
        $this->assertSame(0, (int) Cache::get(ProcessSqsMessages::REJECTION_CACHE_PREFIX . 'stack_account_mismatch', 0));

        // Still visible after a reset, because the messages themselves are untouched.
        $this->assertSame(2, $this->runJson()['quarantined']);
    }
}
