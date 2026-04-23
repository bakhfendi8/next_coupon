<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Prunable;

class CouponEvent extends Model
{
    use Prunable;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'event',            // validated | reserved | consumed | released | failed
        'payload',          // JSON: rule_version, cart_total, reason, etc.
        'coupon_key',
        'occurred_at',
    ];

    protected $casts = [
        'payload'     => 'array',   // JSON column — auto encode/decode
        'occurred_at' => 'datetime',
        'user_id'     => 'integer',
    ];

    // ── Event type constants

    const EVENT_VALIDATED          = 'validated';
    const EVENT_RESERVED           = 'reserved';
    const EVENT_CONSUMED           = 'consumed';
    const EVENT_RELEASED           = 'released';
    const EVENT_VALIDATION_FAILED  = 'validation_failed';
    const EVENT_RESERVATION_FAILED = 'reservation_failed';

    // ── Relationships

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    // ── Scopes

    public function scopeOfType($query, string $event)
    {
        return $query->where('event', $event);
    }

    public function scopeForCoupon($query, int $couponId)
    {
        return $query->where('coupon_id', $couponId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCouponKey($query, string $key)
    {
        return $query->where('coupon_key', 'LIKE', $key . '%');
    }

    /**
     * Find the latest terminal event (reserved / failed) for a given
     * Coupon key — used by the coupon status polling endpoint.
     */
    public function scopeTerminal($query)
    {
        return $query->whereIn('event', [
            self::EVENT_RESERVED,
            self::EVENT_VALIDATION_FAILED,
            self::EVENT_RESERVATION_FAILED,
        ]);
    }

    // ── Prunable

    /**
     * Prune events older than 180 days to keep the table lean.
     * Adjust retention period to match your audit requirements.
     * Run via: php artisan model:prune
     */
    public function prunable()
    {
        return static::where('occurred_at', '<', now()->subDays(180));
    }

    // ── Helpers

    /**
     * Convenience: get a specific payload value.
     *
     * Example: $event->payloadValue('setting_version')
     */
    public function payloadValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }

    /**
     * Check if this is a successful reservation event.
     */
    public function isReserved(): bool
    {
        return $this->event === self::EVENT_RESERVED;
    }

    /**
     * Check if this is any kind of failure event.
     */
    public function isFailed(): bool
    {
        return in_array($this->event, [
            self::EVENT_VALIDATION_FAILED,
            self::EVENT_RESERVATION_FAILED,
        ]);
    }
}