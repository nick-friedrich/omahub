<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep registry metadata and the security review current. Commits are refreshed
// first, then only plugins whose latest commit has no successful scan are
// re-scanned (--stale). No live GitHub fetch happens per page view — the detail
// page reads stored data and marks scans stale when the analyzed commit predates
// the latest known commit.
//
// These entries only run if something triggers Laravel's scheduler. In production
// this box has no queue workers, so add one host cron line:
//   * * * * * docker exec reverse-proxy-fpm-1 php /srv/omahub/artisan schedule:run >> /dev/null 2>&1
Schedule::command('plugins:refresh')
    ->dailyAt('03:10')
    ->withoutOverlapping();

Schedule::command('plugins:scan --stale')
    ->dailyAt('04:10')
    ->withoutOverlapping();
