<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CouponReservationService
{
    /**
     * TTL for a reservation in seconds (5 minutes).
     */
    private const RESERVATION_TTL = 300;

    /**
     * Atomically reserve a coupon slot for a user.
     *
     * Uses a Lua script so the check-and-set is a single atomic operation —
     * safe across multiple application servers with no race conditions.
     *
     * @return bool  true if reserved, false if slot is already taken
     */
    public function reserve(int $couponId, int $userId, string $couponKey): bool
    {
        $reservationKey  = $this->reservationKey($couponId, $userId);
        $couponLock      = $this->couponKey($couponKey);

        // Lua script: SET NX + idempotency guard in one atomic operation.
        // KEYS[1] = reservation key
        // KEYS[2] = coupon lock key
        // ARGV[1] = coupon key value (stored as the reservation payload)
        // ARGV[2] = TTL in seconds
        $lua = <<<'LUA'
        if redis.call("EXISTS", KEYS[2]) == 1 then
            return 2
        end
        local set = redis.call("SET", KEYS[1], ARGV[1], "NX", "EX", ARGV[2])
        if set then
            redis.call("SET", KEYS[2], 1, "EX", ARGV[2])
            return 1
        end
        return 0
        LUA;

        $result = Redis::eval($lua, 2, $reservationKey, $couponLock, $couponKey, self::RESERVATION_TTL);

        // 1 = newly reserved, 2 = already reserved (idempotent replay), 0 = taken by another user
        return in_array($result, [1, 2]);
    }

    /**
     * Release a reservation (on checkout failure or timeout).
     */
    public function release(int $couponId, int $userId): void
    {
        Redis::del($this->reservationKey($couponId, $userId));
    }

    /**
     * Check whether a reservation exists.
     */
    public function isReserved(int $couponId, int $userId): bool
    {
        return (bool) Redis::exists($this->reservationKey($couponId, $userId));
    }

    /**
     * Return remaining TTL in seconds, or null if not reserved.
     */
    public function ttl(int $couponId, int $userId): ?int
    {
        $ttl = Redis::ttl($this->reservationKey($couponId, $userId));
        return $ttl > 0 ? $ttl : null;
    }

    // -------------------------------------------------------------------------
    // Key helpers
    // -------------------------------------------------------------------------

    private function reservationKey(int $couponId, int $userId): string
    {
        return "coupon:reservation:{$couponId}:{$userId}";
    }

    private function couponKey(string $couponKey): string
    {
        return "coupon:idempotency:{$couponKey}";
    }
}