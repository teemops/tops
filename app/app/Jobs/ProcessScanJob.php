<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Models\ScanResult;
use App\Services\AwsSecurityScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScanJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Scan $scan
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Reload scan to ensure we have latest data
        $this->scan->refresh();

        // Check if scan was cancelled
        if ($this->scan->status === 'cancelled') {
            Log::info('Scan cancelled, skipping processing', ['scan_id' => $this->scan->id]);
            return;
        }

        try {
            // Update scan status to running
            $this->scan->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            // Get AWS account
            $awsAccount = $this->scan->awsAccount;

            if (!$awsAccount || $awsAccount->status !== 'active') {
                throw new \Exception('AWS account is not active');
            }

            // Get IAM role ARN and external ID
            $roleArn = $awsAccount->iam_role_arn;
            $externalId = $awsAccount->external_id;

            if (!$roleArn || !$externalId) {
                throw new \Exception('AWS account missing IAM role ARN or external ID');
            }

            // Create scanner and run scan
            $scanner = new AwsSecurityScanner($roleArn, $externalId);
            $findings = $scanner->runFullScan();

            // Store findings
            foreach ($findings as $finding) {
                ScanResult::create([
                    'scan_id' => $this->scan->id,
                    'severity' => $finding['severity'],
                    'service' => $finding['service'],
                    'resource_type' => $finding['resource_type'],
                    'resource_id' => $finding['resource_id'],
                    'finding_type' => $finding['finding_type'],
                    'title' => $finding['title'],
                    'description' => $finding['description'],
                    'remediation' => $finding['remediation'] ?? null,
                    'status' => 'open',
                ]);
            }

            // Update scan status to completed
            $this->scan->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Update AWS account last scan time
            $awsAccount->update([
                'last_scan_at' => now(),
            ]);

            Log::info('Scan completed successfully', [
                'scan_id' => $this->scan->id,
                'findings_count' => count($findings),
            ]);
        } catch (\Exception $e) {
            Log::error('Scan failed', [
                'scan_id' => $this->scan->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update scan status to failed
            $this->scan->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Scan job failed after all retries', [
            'scan_id' => $this->scan->id,
            'error' => $exception->getMessage(),
        ]);

        $this->scan->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => 'Job failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
        ]);
    }
}
