<?php

namespace App\Providers;

use App\Services\FailedJobHandler;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /*
    |--------------------------------------------------------------------------
    | AppServiceProvider
    |--------------------------------------------------------------------------
    | Bootstraps core application services.
    |
    | Responsibilities:
    |   1. Register FailedJobHandler for all permanently failed jobs
    |   2. Set sensible model defaults
    */

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ── Strict model behaviour ────────────────────────────────────────────
        // Prevent mass assignment and lazy loading issues silently.
        \Illuminate\Database\Eloquent\Model::shouldBeStrict(
            !app()->isProduction()
        );

        // ── Failed job handler ────────────────────────────────────────────────
        // Fires when any job exhausts all retry attempts.
        // Handles logging, alerting, and graceful recovery per job type.
        Queue::failing(function (JobFailed $event) {
            app(FailedJobHandler::class)->handle($event);
        });
    }
}