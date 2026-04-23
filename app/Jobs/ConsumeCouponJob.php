<?php

namespace App\Jobs;

use App\Models\CouponUsage;
use App\Services\CouponReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsumeCouponJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Queue: default
    | Triggered after successful checkout.
    | Writes permanent usage record to MySQL and releases Redis reservation.
    |--------------------------------------------------------------------------
    | tries   = 5   — retry aggressively; a missed consume means the coupon
    |                 stays "reserved" forever which is worse than a duplicate write
    | backoff = 3s
    | timeout = 30s
    */

    public int    $tries   = 5;
    public int    $timeout = 30;
    public int    $backoff = 3;

    public function __construct(
        public readonly int    $couponId,
        public readonly int    $userId,
        public readonly int    $orderId,
        public readonly string $couponKey,
    ) {
        $this->onQueue('default');
    }

    public function handle(CouponReservationService $reservations): void
    {
        // ── Idempotency guard ─────────────────────────────────────────────────
        // If this exact coupon+user+order was already consumed (e.g. on a retry),
        // skip the DB write but still ensure the Redis key is cleaned up.
        $alreadyConsumed = CouponUsage::where('coupon_id', $this->couponId)
            ->where('user_id',  $this->userId)
            ->where('order_id', $this->orderId)
            ->where('status',   'consumed')
            ->exists();

        if ($alreadyConsumed) {
            Log::info('[ConsumeCouponJob] Already consumed — skipping DB write', [
                'coupon_id' => $this->couponId,
                'order_id'  => $this->orderId,
            ]);
            $reservations->release($this->couponId, $this->userId);
            return;
        }

        // ── Atomic: write to MySQL + release Redis ────────────────────────────
        // Both operations are wrapped in a transaction.
        // If the DB write fails, Redis is NOT released — job will retry.
        // If the DB write succeeds but Redis release fails, the TTL cleans up Redis.
        DB::transaction(function () use ($reservations) {
            CouponUsage::create([
                'coupon_id'   => $this->couponId,
                'user_id'     => $this->userId,
                'order_id'    => $this->orderId,
                'status'      => 'consumed',
                'consumed_at' => now(),
            ]);

            $reservations->release($this->couponId, $this->userId);
        });

        // ── Record 'consumed' event ───────────────────────────────────────────
        RecordCouponEventJob::dispatch(
            couponId:       $this->couponId,
            userId:         $this->userId,
            event:          'consumed',
            payload:        ['order_id' => $this->orderId],
            couponKey: "{$this->couponKey}:consumed",
        )->onQueue('low');

        Log::info('[ConsumeCouponJob] Coupon consumed successfully', [
            'coupon_id' => $this->couponId,
            'user_id'   => $this->userId,
            'order_id'  => $this->orderId,
        ]);
    }

    /**
     * Called when all retry attempts are exhausted.
     * This is critical — a missed consume means the coupon slot may be lost.
     * Flag for manual reconciliation.
     */
    public function failed(\Throwable $e): void
    {
        Log::critical('[ConsumeCouponJob] PERMANENTLY FAILED — manual reconciliation required', [
            'coupon_id'       => $this->couponId,
            'user_id'         => $this->userId,
            'order_id'        => $this->orderId,
            'coupon_key'      => $this->couponKey,
            'error'           => $e->getMessage(),
        ]);

        // Write a resolution queue record so ops team can review.
        DB::table('resolution_queue')->insertOrIgnore([
            'type'       => 'coupon_consume_failure',
            'payload'    => json_encode([
                'coupon_id'       => $this->couponId,
                'user_id'         => $this->userId,
                'order_id'        => $this->orderId,
                'coupon_key'      => $this->couponKey,
                'error'           => $e->getMessage(),
            ]),
            'created_at' => now(),
        ]);
    }
}