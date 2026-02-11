<?php

namespace App\Console\Commands;

use App\Models\Scan;
use Illuminate\Console\Command;

class MarkStaleRegionScansComplete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scans:mark-stale-region-complete
                            {--minutes=60 : Consider scans stale after this many minutes}
                            {--dry-run : List scans that would be updated without changing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark long-running scans as completed. Only scans with region-based services (e.g. EC2, RDS) are updated; others are left running.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = $this->option('dry-run');

        $cutoff = now()->subMinutes($minutes);
        $scans = Scan::where('status', 'running')
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $cutoff)
            ->get();

        if ($scans->isEmpty()) {
            $this->info("No scans found that have been running for more than {$minutes} minutes.");
            return self::SUCCESS;
        }

        $this->info('Found ' . $scans->count() . ' scan(s) that have been running for more than ' . $minutes . ' minutes.');

        foreach ($scans as $scan) {
            $this->line("  Scan {$scan->id} (started {$scan->started_at->diffForHumans()})");
        }

        if ($dryRun) {
            $this->warn('Dry run: no changes made. Run without --dry-run to mark these as completed.');
            return self::SUCCESS;
        }

        foreach ($scans as $scan) {
            $scan->checkAndMarkRegionBasedScanComplete();
            $this->info("Processed scan {$scan->id}.");
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
