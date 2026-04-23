<?php

namespace App\Services;

use App\Jobs\RecordCouponEventJob;
use App\Services\CouponReservationService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FailedJobHandler
 *
 * Central handler for all permanently failed queue jobs.
 * Registered in AppServiceProvider::boot() via Queue::failing().
 *
 * Responsibilities:
 *   1. Log all failures with full context
 *   2. Alert (via log) for critical job failures
 *   3. Attempt graceful self-recovery per job type:
 *      - ValidateCouponJob → release any ghost Redis reservation
 *      - ConsumeCouponJob  → flag for manual reconciliation
 */
class FailedJobHandler
{
    /*
    |--------------------------------------------------------------------------
    | Critical Jobs
    |--------------------------------------------------------------------------
    | These jobs trigger a CRITICAL log entry when they fail permanently.
    | In production, hook this up to Slack or PagerDuty for alerting.
    */

    private array $criticalJobs = [
        \App\Jobs\ConsumeCouponJob::class,
        \App\Jobs\ValidateCouponJob::class,
    ];

    public function handle(JobFailed $event): void
    {
        $jobName   = $event->job->getName();
        $jobClass  = $this->resolveJobClass($event);
        $exception = $event->exception;
        $payload   = $event->job->payload();

        // ── 1. Log all failures ───────────────────────────────────────────────
        Log::error('[FailedJobHandler] Job permanently failed', [
            'job'       => $jobName,
            'queue'     => $event->job->getQueue(),
            'payload'   => $payload,
            'error'     => $exception->getMessage(),
            'exception' => get_class($exception),
        ]);

        // ── 2. Alert for critical jobs ────────────────────────────────────────
        if (in_array($jobClass, $this->criticalJobs)) {
            $this->alertCritical($jobClass, $payload, $exception);
        }

        // ── 3. Self-recovery per job type ─────────────────────────────────────
        $this->attemptRecovery($jobClass, $payload, $exception);
    }

    // ── Alert ─────────────────────────────────────────────────────────────────

    private function alertCritical(
        string     $jobClass,
        array      $payload,
        \Throwable $exception,
    ): void {
        // Logs as CRITICAL so it surfaces in any log aggregator (Papertrail, etc.)
        // To add Slack/email: install a notification package and uncomment below.
        Log::critical('[FailedJobHandler] CRITICAL JOB FAILURE', [
            'job'     => $jobClass,
            'error'   => $exception->getMessage(),
            'payload' => $payload,
            'action'  => 'Manual intervention may be required.',
        ]);

        // ── Optional: Slack notification ──────────────────────────────────────
        // Uncomment after running: composer require laravel/slack-notification-channel
        //
        // \Illuminate\Support\Facades\Notification::route('slack', env('HORIZON_SLACK_WEBHOOK'))
        //     ->notify(new \App\Notifications\JobFailedNotification(
        //         jobClass: $jobClass,
        //         error:    $exception->getMessage(),
        //         payload:  $payload,
        //     ));
    }

    // ── Recovery ──────────────────────────────────────────────────────────────

    private function attemptRecovery(
        string     $jobClass,
        array      $payload,
        \Throwable $exception,
    ): void {
        match ($jobClass) {
            \App\Jobs\ValidateCouponJob::class => $this->recoverValidateJob($payload, $exception),
            \App\Jobs\ConsumeCouponJob::class  => $this->recoverConsumeJob($payload, $exception),
            default                            => null,
        };
    }

    /**
     * ValidateCouponJob recovery.
     *
     * If the job failed AFTER reserving in Redis but BEFORE completing,
     * there may be a ghost reservation. Release it immediately so the user
     * is not blocked for 5 minutes waiting for the TTL to expire.
     */
    private function recoverValidateJob(array $payload, \Throwable $exception): void
    {
        $data     = $payload['data'] ?? [];
        $couponId = $data['couponId'] ?? null;
        $userId   = $data['userId'] ?? null;

        if (! $couponId || ! $userId) {
            Log::warning('[FailedJobHandler] Cannot recover ValidateCouponJob — missing payload data');
            return;
        }

        // Release any ghost Redis reservation
        app(CouponReservationService::class)->release((int) $couponId, (int) $userId);

        // Record a released event so the audit trail is complete
        RecordCouponEventJob::dispatchSync(
            couponId:       (int) $couponId,
            userId:         (int) $userId,
            event:          'released',
            payload:        [
                'reason' => 'validate_job_permanently_failed',
                'error'  => $exception->getMessage(),
            ],
            couponKey: "recovery:validate:{$couponId}:{$userId}:" . now()->timestamp,
        );

        Log::info('[FailedJobHandler] Released ghost reservation after ValidateCouponJob failure', [
            'coupon_id' => $couponId,
            'user_id'   => $userId,
        ]);
    }

    /**
     * ConsumeCouponJob recovery.
     *
     * A permanently failed consume means the coupon was reserved and the order
     * was placed, but the permanent MySQL record was never written.
     * Flag for manual reconciliation — a human needs to verify the order.
     */
    private function recoverConsumeJob(array $payload, \Throwable $exception): void
    {
        $data = $payload['data'] ?? [];

        // Write to a resolution_queue table for ops team to review.
        // Create this table if it doesn't exist yet:
        //   Schema::create('resolution_queue', function (Blueprint $table) {
        //       $table->id();
        //       $table->string('type');
        //       $table->json('payload');
        //       $table->boolean('resolved')->default(false);
        //       $table->timestamps();
        //   });
        try {
            DB::table('resolution_queue')->insert([
                'type'       => 'coupon_consume_failure',
                'payload'    => json_encode([
                    'coupon_id'       => $data['couponId'] ?? null,
                    'user_id'         => $data['userId'] ?? null,
                    'order_id'        => $data['orderId'] ?? null,
                    'coupon_key'      => $data['couponKey'] ?? null,
                    'error'           => $exception->getMessage(),
                    'failed_at'       => now()->toISOString(),
                ]),
                'resolved'   => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('[FailedJobHandler] ConsumeCouponJob failure flagged for reconciliation', [
                'coupon_id' => $data['couponId'] ?? null,
                'order_id'  => $data['orderId']  ?? null,
            ]);
        } catch (\Throwable $e) {
            // If even the reconciliation write fails, at least it's in the log.
            Log::critical('[FailedJobHandler] Could not write to resolution_queue', [
                'error'   => $e->getMessage(),
                'payload' => $data,
            ]);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Resolve the actual PHP class name from the job event payload.
     * The job name in the payload is the full class name for non-closure jobs.
     */
    private function resolveJobClass(JobFailed $event): string
    {
        $payload = $event->job->payload();
        return $payload['displayName'] ?? $event->job->getName() ?? '';
    }
}