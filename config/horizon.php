<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    | Leave null to serve Horizon on the same domain as your app.
    | Set to e.g. "horizon.yourdomain.com" for a dedicated subdomain.
    */

    'domain' => env('HORIZON_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    | The URI where Horizon's dashboard is accessible.
    | Dashboard URL: http://localhost:8000/horizon
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Redis Connection
    |--------------------------------------------------------------------------
    | Must match a connection defined in config/database.php under 'redis'.
    | Default Laravel setup uses 'default' — no changes needed.
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Key Prefix
    |--------------------------------------------------------------------------
    | All Horizon keys in Redis are prefixed with this value.
    | Useful when sharing a Redis instance across multiple projects.
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Blocking
    |--------------------------------------------------------------------------
    | How long (seconds) a worker blocks on Redis BLPOP waiting for jobs.
    | Lower = more responsive, higher CPU. Higher = more efficient, more latency.
    */

    'waits' => [
        'redis:high'    => 3,
        'redis:default' => 5,
        'redis:low'     => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Record Retention
    |--------------------------------------------------------------------------
    | How long (minutes) Horizon keeps job records in Redis.
    | Failed jobs are kept longer for debugging and manual retry.
    */

    'trim' => [
        'recent'        => 60,      // 1 hour
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,   // 7 days
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    | These jobs are processed normally but hidden from the dashboard UI.
    | RecordCouponEventJob runs constantly — hiding it reduces noise.
    */

    'silenced' => [
        \App\Jobs\RecordCouponEventJob::class,
        \App\Jobs\CleanExpiredReservationsJob::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics Snapshots
    |--------------------------------------------------------------------------
    | How many hours of throughput/runtime metrics to retain.
    */

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    | false = wait for the current job to finish before stopping worker.
    | Keep false — ConsumeCouponJob holds a DB transaction.
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit
    |--------------------------------------------------------------------------
    | Workers restart automatically if they exceed this limit (MB).
    */

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Supervisors
    |--------------------------------------------------------------------------
    |
    | Three supervisors — one per queue tier.
    | Each supervisor manages its own worker pool independently.
    | This prevents low-priority analytics jobs from starving validation.
    |
    | Supervisor overview:
    |
    |  coupon-high     queue: high    → ValidateCouponJob
    |  coupon-default  queue: default → ConsumeCouponJob, ReleaseCouponJob,
    |                                   UpdateCartJob
    |  coupon-low      queue: low     → RecordCouponEventJob,
    |                                   CleanExpiredReservationsJob
    |
    | Key settings explained:
    |   balance              auto   = scale workers based on queue metrics
    |                        simple = fixed worker count, no scaling
    |                        false  = all processes on single supervisor
    |   autoScalingStrategy  time   = scale on how long jobs wait
    |                        size   = scale on queue depth
    |   minProcesses         always keep this many workers alive
    |   maxProcesses         never exceed this many workers
    |   balanceMaxShift      max workers to add/remove per scaling cycle
    |   balanceCooldown      seconds to wait between scaling decisions
    |   sleep                seconds to wait when queue is empty
    |   timeout              kill job if it runs longer than this (seconds)
    |
    */

    'environments' => [

        // ── Production ────────────────────────────────────────────────────────
        'production' => [

            // Tier 1: High priority — user is actively waiting
            'coupon-high' => [
                'connection'          => 'redis',
                'queue'               => ['high'],
                'balance'             => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses'        => 2,
                'maxProcesses'        => 20,
                'balanceMaxShift'     => 5,
                'balanceCooldown'     => 3,
                'tries'               => 3,
                'timeout'             => 30,
                'memory'              => 128,
                'sleep'               => 1,
                'force'               => false,
            ],

            // Tier 2: Default — cart, consume, release
            'coupon-default' => [
                'connection'          => 'redis',
                'queue'               => ['default'],
                'balance'             => 'auto',
                'autoScalingStrategy' => 'size',
                'minProcesses'        => 2,
                'maxProcesses'        => 10,
                'balanceMaxShift'     => 3,
                'balanceCooldown'     => 5,
                'tries'               => 5,
                'timeout'             => 60,
                'memory'              => 128,
                'sleep'               => 2,
                'force'               => false,
            ],

            // Tier 3: Low priority — analytics and cleanup
            'coupon-low' => [
                'connection'   => 'redis',
                'queue'        => ['low'],
                'balance'      => 'simple',
                'minProcesses' => 1,
                'maxProcesses' => 3,
                'tries'        => 5,
                'timeout'      => 60,
                'memory'       => 64,
                'sleep'        => 5,
                'force'        => false,
            ],

        ],

        // ── Staging ───────────────────────────────────────────────────────────
        'staging' => [

            'coupon-staging' => [
                'connection'   => 'redis',
                'queue'        => ['high', 'default', 'low'],
                'balance'      => 'simple',
                'minProcesses' => 1,
                'maxProcesses' => 5,
                'tries'        => 3,
                'timeout'      => 60,
                'memory'       => 128,
                'sleep'        => 3,
                'force'        => false,
            ],

        ],

        // ── Local (Windows/XAMPP) ─────────────────────────────────────────────
        // Note: php artisan horizon does NOT work on Windows due to missing
        // ext-pcntl and ext-posix extensions.
        // Use this instead:
        //   php artisan queue:work redis --queue=high,default,low
        //
        // The 'local' config below is kept for reference when running on Linux/Mac.
        'local' => [

            'coupon-local' => [
                'connection'   => 'redis',
                'queue'        => ['high', 'default', 'low'],
                'balance'      => 'false',
                'processes'    => 3,
                'tries'        => 1,
                'timeout'      => 60,
                'memory'       => 256,
                'sleep'        => 3,
                'force'        => false,
            ],

        ],

    ],

];