<?php

namespace App\Console;

use App\Jobs\CleanExpiredReservationsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /*
    |--------------------------------------------------------------------------
    | Scheduled Tasks
    |--------------------------------------------------------------------------
    | Add this single cron entry to your server:
    |
    |   * * * * * cd /var/www/next_coupon && php artisan schedule:run >> /dev/null 2>&1
    |
    | On Windows (local dev), run instead:
    |   php artisan schedule:work
    */

    protected function schedule(Schedule $schedule): void
    {
        // ── Clean expired/orphaned coupon reservations ────────────────────────
        // Runs every minute. Covers two failure modes:
        //   1. Redis keys with no TTL (should never happen but defensive)
        //   2. MySQL 'reserved' events with no 'consumed'/'released' follow-up
        $schedule->job(new CleanExpiredReservationsJob, 'low')
            ->everyMinute()
            ->withoutOverlapping(5)   // skip if previous run still active
            ->runInBackground()
            ->onFailure(function () {
                \Log::error('CleanExpiredReservationsJob scheduled run failed');
            });

        // ── Horizon metrics snapshot ──────────────────────────────────────────
        $schedule->command('horizon:snapshot')
            ->everyFiveMinutes();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}