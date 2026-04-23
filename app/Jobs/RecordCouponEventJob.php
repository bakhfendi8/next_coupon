<?php

namespace App\Jobs;

use App\Models\CouponEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecordCouponEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Queue: low
    | Async audit logging — never blocks validation or checkout.
    | Runs last priority so it never starves higher-priority jobs.
    |--------------------------------------------------------------------------
    | Event types recorded:
    |   validated          — rule engine ran (valid or not)
    |   validation_failed  — rule check failed
    |   reservation_failed — concurrent conflict in Redis
    |   reserved           — Redis slot claimed successfully
    |   consumed           — permanent MySQL record written
    |   released           — reservation freed
    |
    | Idempotency: uses insertOrIgnore + unique coupon_key column
    | to prevent duplicate rows on job retries.
    */

    public int    $tries   = 5;
    public int    $timeout = 20;
    public int    $backoff = 10;

    public function __construct(
        public readonly int    $couponId,
        public readonly int    $userId,
        public readonly string $event,          // see event types above
        public readonly array  $payload,        // context: rule_version, cart_total, reason, etc.
        public readonly string $couponKey, // unique per event instance
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        // insertOrIgnore: if a row with this coupon_key already exists
        // (e.g. from a previous retry), the insert is silently skipped.
        // Requires a UNIQUE index on coupon_events.coupon_key.
        CouponEvent::insertOrIgnore([
            'coupon_id'       => $this->couponId,
            'user_id'         => $this->userId,
            'event'           => $this->event,
            'payload'         => json_encode($this->payload),
            'coupon_key' => $this->couponKey,
            'occurred_at'     => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        Log::debug('[RecordCouponEventJob] Event recorded', [
            'coupon_id' => $this->couponId,
            'event'     => $this->event,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        // Analytics job failure is non-critical — log and move on.
        // The business flow is unaffected even if event recording fails.
        Log::warning('[RecordCouponEventJob] Failed to record event', [
            'coupon_id'       => $this->couponId,
            'event'           => $this->event,
            'coupon_key'      => $this->couponKey,
            'error'           => $e->getMessage(),
        ]);
    }
}