<?php

namespace App\Console\Commands;

use App\Models\Scan;
use App\Services\ScanTypesService;
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
    protected $description = 'Mark long-running region-based scans as completed and partial. A fallback for orphaned batches only; the normal completion path is the batch callback.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = $this->option('dry-run');

        // 'pending' is included deliberately: if the orchestrator job died before
        // flipping the scan to 'running', its region jobs still report in and
        // nothing else would ever complete it.
        $cutoff = now()->subMinutes($minutes);
        $scans = Scan::whereIn('status', ['pending', 'running'])
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $cutoff)
            ->get()
            // Region-based only. A scan without region services is completed inline by
            // the orchestrator, so one still running is a different fault and settling it
            // here would hide that. This filter used to live inside the model method this
            // command called; it is explicit now that the command decides.
            ->filter(fn (Scan $scan) => !empty(array_filter(
                $scan->scan_types ?? [],
                fn (string $type) => ScanTypesService::isRegionBased($type)
            )));

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
            // Anything this sweep settles is partial, by definition: it only runs when
            // the batch callback did not, which means work is unaccounted for. The old
            // code decided partial-or-not from a counter, and could decide "not partial"
            // for a scan that had barely started — a clean bill of health for regions
            // nobody looked at. A fallback path must never be able to say "all clear".
            $settled = $scan->settleRegionScan(
                true,
                "Scan exceeded {$minutes} minutes without completing; results are partial."
            );

            $this->info($settled
                ? "Settled scan {$scan->id} as partial."
                : "Scan {$scan->id} was already settled; left alone.");
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
