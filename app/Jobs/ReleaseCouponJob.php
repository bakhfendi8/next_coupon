<?php

namespace App\Jobs;

use App\Services\CouponReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReleaseCouponJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Queue: default
    | Triggered when checkout fails, user cancels, or session expires.
    | Frees the Redis reservation so other users can claim the coupon.
    |--------------------------------------------------------------------------
    | tries   = 3
    | backoff = 5s
    | timeout = 15s — simple Redis DEL, should be very fast
    */

    public int    $tries   = 3;
    public int    $timeout = 15;
    public int    $backoff = 5;

    public function __construct(
        public readonly int    $couponId,
        public readonly int    $userId,
        public readonly string $couponKey,
        public readonly string $reason = 'checkout_failed',
        // Possible reasons:
        //   checkout_failed   — payment declined
        //   user_cancelled    — user removed the coupon
        //   session_expired   — browser session ended
        //   job_failed        — system error in upstream job
    ) {
        $this->onQueue('default');
    }

    public function handle(CouponReservationService $reservations): void
    {
        // Redis DEL is idempotent — safe to call multiple times.
        // If the key doesn't exist (already expired or released), this is a no-op.
        $reservations->release($this->couponId, $this->userId);

        // ── Record 'released' event ───────────────────────────────────────────
        RecordCouponEventJob::dispatch(
            couponId:       $this->couponId,
            userId:         $this->userId,
            event:          'released',
            payload:        ['reason' => $this->reason],
            couponKey: "{$this->couponKey}:released",
        )->onQueue('low');

        Log::info('[ReleaseCouponJob] Reservation released', [
            'coupon_id' => $this->couponId,
            'user_id'   => $this->userId,
            'reason'    => $this->reason,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        // If release permanently fails, the Redis TTL (5 min) will clean it up anyway.
        // Log for visibility but no critical action needed.
        Log::error('[ReleaseCouponJob] Failed to release reservation', [
            'coupon_id' => $this->couponId,
            'user_id'   => $this->userId,
            'reason'    => $this->reason,
            'error'     => $e->getMessage(),
        ]);
    }
}