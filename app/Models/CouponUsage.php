<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponUsage extends Model
{
    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id',
        'status',       // consumed | released
        'consumed_at',
    ];

    protected $casts = [
        'consumed_at' => 'datetime',
        'user_id'     => 'integer',
        'order_id'    => 'integer',
    ];

    // ── Relationships

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    // ── Scopes

    /**
     * Only consumed (permanent) usages.
     */
    public function scopeConsumed($query)
    {
        return $query->where('status', 'consumed');
    }

    /**
     * Only released usages.
     */
    public function scopeReleased($query)
    {
        return $query->where('status', 'released');
    }

    /**
     * Filter by user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filter by coupon.
     */
    public function scopeForCoupon($query, int $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    // ── Helpers

    /**
     * How many times has a specific user consumed a specific coupon.
     */
    public static function countForUser(int $couponId, int $userId): int
    {
        return static::forCoupon($couponId)
            ->forUser($userId)
            ->consumed()
            ->count();
    }

    /**
     * Total global consumed count for a coupon.
     */
    public static function globalCount(int $couponId): int
    {
        return static::forCoupon($couponId)
            ->consumed()
            ->count();
    }
}