<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Safety net for region-based scans (EC2/RDS): if a region job never reports back
// (a failed retry, a lost SQS message), the scan would otherwise stay "running"
// forever. This periodically re-checks and completes/times-out stale scans.
Schedule::command('scans:mark-stale-region-complete')->everyFiveMinutes()->withoutOverlapping();
