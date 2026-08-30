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
// Refresh every hour. Unchanged plugins are skipped cheaply via GitHub ETag /
// If-None-Match (304 responses do not count against the API rate limit), so an
// hourly run stays well inside a 5k/hour token quota even with ~1k plugins.
//
// The advisory AI review runs after the deterministic scan, also hourly, and
// also targets only plugins whose latest commit has no successful review yet
// (--stale). Because it is idempotent per commit, unchanged plugins are skipped
// and cost stays bounded to new commits (roughly a fraction of a cent each).
//
// These entries only run if something triggers Laravel's scheduler. In production
// this box has no queue workers, so add one host cron line:
//   * * * * * docker exec reverse-proxy-fpm-1 php /srv/omahub/artisan schedule:run >> /dev/null 2>&1
Schedule::command('plugins:refresh')
    ->hourlyAt(10)
    ->withoutOverlapping();

Schedule::command('plugins:scan --stale')
    ->hourlyAt(40)
    ->withoutOverlapping();

Schedule::command('plugins:ai-review --stale')
    ->hourlyAt(50)
    ->withoutOverlapping();
