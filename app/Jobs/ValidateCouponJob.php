<?php

namespace App\Jobs;

use App\Models\Coupon;
use App\Services\CouponReservationService;
use App\Services\CouponRuleEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ValidateCouponJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Queue: high
    | Runs first — user is actively waiting for the validation result.
    |--------------------------------------------------------------------------
    | Retry table:
    |   tries   = 3   — attempt up to 3 times
    |   backoff = 5s  — wait 5s between retries
    |   timeout = 30s — kill if job hangs longer than 30s
    */

    public int    $tries   = 3;
    public int    $timeout = 30;
    public int    $backoff = 5;

    public function __construct(
        public readonly int    $couponId,
        public readonly int    $userId,        // guest session-based ID — no auth required
        public readonly array  $cart,          // ['total' => 247.00, 'items' => [...]]
        public readonly string $couponKey, // "{userId}:{couponId}:{cartHash}"
    ) {
        $this->onQueue('high');
    }

    public function handle(
        CouponRuleEngine         $ruleEngine,
        CouponReservationService $reservations,
    ): void {
        // ── 1. Idempotency guard ──────────────────────────────────────────────
        // If job was retried, skip re-processing — result is already in cache.
        $cacheKey = "coupon:job_result:{$this->couponKey}";
        if (Cache::has($cacheKey)) {
            Log::info('[ValidateCouponJob] Skipping duplicate', ['key' => $this->couponKey]);
            return;
        }

        $coupon = Coupon::findOrFail($this->couponId);

        // ── 2. Rule evaluation ────────────────────────────────────────────────
        // CouponRuleEngine always fetches the LATEST active CouponSetting version.
        // Rules changed after dispatch are applied correctly.
        $result = $ruleEngine->evaluate($coupon, $this->userId, $this->cart);

        // Cache result so subsequent retries are no-ops.
        Cache::put($cacheKey, $result, now()->addMinutes(10));

        // ── 3. Record 'validated' event ───────────────────────────────────────
        RecordCouponEventJob::dispatch(
            couponId:       $this->couponId,
            userId:         $this->userId,
            event:          'validated',
            payload:        array_merge($result, ['cart_total' => $this->cart['total']]),
            couponKey: $this->couponKey,
        )->onQueue('low');

        // ── 4. Handle invalid coupon ──────────────────────────────────────────
        if (! $result['valid']) {
            Log::info('[ValidateCouponJob] Coupon invalid', [
                'coupon_id'   => $this->couponId,
                'user_id'     => $this->userId,
                'reason'      => $result['reason'],
                'rule_failed' => $result['rule_failed'],
            ]);

            RecordCouponEventJob::dispatch(
                couponId:       $this->couponId,
                userId:         $this->userId,
                event:          'validation_failed',
                payload:        [
                    'reason'      => $result['reason'],
                    'rule_failed' => $result['rule_failed'],
                ],
                couponKey: "{$this->couponKey}:validation_failed",
            )->onQueue('low');

            return;
        }

        // ── 5. Atomic Redis reservation ───────────────────────────────────────
        // Lua script ensures check-and-set is a single atomic Redis operation.
        // Safe across multiple application servers — no race condition possible.
        $reserved = $reservations->reserve(
            $this->couponId,
            $this->userId,
            $this->couponKey,
        );

        if (! $reserved) {
            Log::warning('[ValidateCouponJob] Reservation conflict — slot taken', [
                'coupon_id' => $this->couponId,
                'user_id'   => $this->userId,
            ]);

            RecordCouponEventJob::dispatch(
                couponId:       $this->couponId,
                userId:         $this->userId,
                event:          'reservation_failed',
                payload:        ['reason' => 'concurrent_conflict'],
                couponKey: "{$this->couponKey}:reservation_failed",
            )->onQueue('low');

            return;
        }

        // ── 6. Record 'reserved' event ────────────────────────────────────────
        RecordCouponEventJob::dispatch(
            couponId:       $this->couponId,
            userId:         $this->userId,
            event:          'reserved',
            payload:        [
                'setting_version' => $result['setting_version'],
                'ttl_seconds'     => 300,
            ],
            couponKey: "{$this->couponKey}:reserved",
        )->onQueue('low');

        // ── 7. Dispatch cart update ───────────────────────────────────────────
        UpdateCartJob::dispatch(
            userId:         $this->userId,
            cart:           $this->cart,
            couponId:       $this->couponId,
            couponKey: $this->couponKey,
        )->onQueue('default');

        Log::info('[ValidateCouponJob] Complete — coupon reserved', [
            'coupon_id'       => $this->couponId,
            'user_id'         => $this->userId,
            'setting_version' => $result['setting_version'],
        ]);
    }

    /**
     * Called when all retry attempts are exhausted.
     * Releases any ghost Redis reservation and records the failure event.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('[ValidateCouponJob] Permanently failed', [
            'coupon_id'       => $this->couponId,
            'user_id'         => $this->userId,
            'coupon_key'      => $this->couponKey,
            'error'           => $e->getMessage(),
        ]);

        // Release any partial reservation that may have been set before the crash.
        app(CouponReservationService::class)->release($this->couponId, $this->userId);

        RecordCouponEventJob::dispatchSync(
            couponId:       $this->couponId,
            userId:         $this->userId,
            event:          'released',
            payload:        ['reason' => 'job_permanently_failed', 'error' => $e->getMessage()],
            couponKey:      "{$this->couponKey}:job_failed",
        );
    }
}