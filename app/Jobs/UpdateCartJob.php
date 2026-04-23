<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateCartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Queue: default
    | Dispatched by ValidateCouponJob after a successful reservation.
    | Stores the discounted cart state in Cache so the frontend can
    | read the updated total without re-calculating on every poll.
    |--------------------------------------------------------------------------
    | tries   = 3
    | backoff = 3s
    | timeout = 20s
    */

    public int    $tries   = 3;
    public int    $timeout = 20;
    public int    $backoff = 3;

    public function __construct(
        public readonly int    $userId,
        public readonly array  $cart,          // original cart from session
        public readonly int    $couponId,
        public readonly string $couponKey,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // ── Idempotency guard ─────────────────────────────────────────────────
        $cacheKey = "cart:updated:{$this->couponKey}";
        if (Cache::has($cacheKey)) {
            Log::info('[UpdateCartJob] Skipping duplicate', ['key' => $this->couponKey]);
            return;
        }

        // ── Load coupon to calculate discount ─────────────────────────────────
        $coupon = \App\Models\Coupon::find($this->couponId);
        if (! $coupon) {
            Log::warning('[UpdateCartJob] Coupon not found', ['coupon_id' => $this->couponId]);
            return;
        }

        $originalTotal = (float) $this->cart['total'];

        // ── Calculate discount ────────────────────────────────────────────────
        $discount = match($coupon->type) {
            'percentage' => round($originalTotal * ($coupon->value / 100), 2),
            'fixed'      => min((float) $coupon->value, $originalTotal),
            default      => 0.0,
        };

        $discountedTotal = max(0, $originalTotal - $discount);

        // ── Store updated cart state in cache ─────────────────────────────────
        // Frontend polls this via /api/coupon/status and reads the updated total.
        // TTL matches the Redis reservation TTL (5 minutes).
        $updatedCart = array_merge($this->cart, [
            'original_total'  => $originalTotal,
            'discount'        => $discount,
            'discounted_total'=> $discountedTotal,
            'coupon_id'       => $this->couponId,
            'coupon_code'     => $coupon->code,
            'coupon_type'     => $coupon->type,
            'coupon_value'    => $coupon->value,
        ]);

        Cache::put(
            "cart:coupon:{$this->userId}",
            $updatedCart,
            now()->addMinutes(5),
        );

        // Mark as processed so retries are skipped.
        Cache::put($cacheKey, true, now()->addMinutes(10));

        Log::info('[UpdateCartJob] Cart updated with discount', [
            'user_id'          => $this->userId,
            'coupon_id'        => $this->couponId,
            'original_total'   => $originalTotal,
            'discount'         => $discount,
            'discounted_total' => $discountedTotal,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        // Non-critical — frontend calculates discount itself anyway.
        // Log for visibility only.
        Log::error('[UpdateCartJob] Failed to update cart cache', [
            'user_id'         => $this->userId,
            'coupon_id'       => $this->couponId,
            'coupon_key'      => $this->couponKey,
            'error'           => $e->getMessage(),
        ]);
    }
}