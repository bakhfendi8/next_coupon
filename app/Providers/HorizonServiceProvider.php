<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /*
    |--------------------------------------------------------------------------
    | HorizonServiceProvider
    |--------------------------------------------------------------------------
    | Registered in bootstrap/providers.php.
    | Controls dashboard access, alerts, and Horizon behaviour.
    |
    | Dashboard URL: http://localhost:8000/horizon
    */

    public function boot(): void
    {
        parent::boot();

        /*
        |----------------------------------------------------------------------
        | Dashboard Theme
        |----------------------------------------------------------------------
        */

        Horizon::night(); // Dark mode

        /*
        |----------------------------------------------------------------------
        | Notification Channels
        |----------------------------------------------------------------------
        | Configure where Horizon sends alerts when a job permanently fails
        | or a queue wait time exceeds a threshold.
        |
        | Set HORIZON_MAIL_TO and HORIZON_SLACK_WEBHOOK in your .env file.
        | Leave empty to disable notifications.
        */

        if (env('HORIZON_MAIL_TO')) {
            Horizon::routeMailNotificationsTo(env('HORIZON_MAIL_TO'));
        }

        if (env('HORIZON_SLACK_WEBHOOK')) {
            Horizon::routeSlackNotificationsTo(
                env('HORIZON_SLACK_WEBHOOK'),
                env('HORIZON_SLACK_CHANNEL', '#coupon-alerts')
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Horizon Access Gate
    |--------------------------------------------------------------------------
    | Controls who can view the /horizon dashboard.
    |
    | Since this project has no auth system, the gate uses one of two modes
    | depending on the APP_ENV:
    |
    |   local / testing  → everyone can access (no restrictions)
    |   production       → only IPs listed in HORIZON_ALLOWED_IPS can access
    |
    | To restrict by IP in production, set in .env:
    |   HORIZON_ALLOWED_IPS=123.456.789.000,111.222.333.444
    |
    | To restrict by email once you add auth later, swap the gate logic below
    | to check $user->email against HORIZON_ALLOWED_EMAILS.
    */

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {

            // ── Local/testing: allow all access ──────────────────────────────
            if (in_array(app()->environment(), ['local', 'testing'])) {
                return true;
            }

            // ── Production: restrict by IP ────────────────────────────────────
            $allowedIps = array_filter(
                explode(',', env('HORIZON_ALLOWED_IPS', '127.0.0.1'))
            );

            return in_array(request()->ip(), $allowedIps);
        });
    }
}