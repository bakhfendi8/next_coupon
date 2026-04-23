<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',     // percentage | fixed | free_shipping
        'value',
    ];

    protected $casts = [
        'value' => 'decimal:2',
    ];

    // ── Relationships

    /**
     * All versioned settings for this coupon.
     */
    public function settings(): HasMany
    {
        return $this->hasMany(CouponSetting::class);
    }

    /**
     * The currently active setting (is_active = true).
     */
    public function activeSetting(): HasOne
    {
        return $this->hasOne(CouponSetting::class)
            ->where('is_active', true)
            ->latestOfMany('version');
    }

    /**
     * All usage records for this coupon.
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * All lifecycle events for this coupon.
     */
    public function events(): HasMany
    {
        return $this->hasMany(CouponEvent::class);
    }

    // ── Scopes

    /**
     * Scope to find a coupon by its code (case-insensitive).
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }

    // ── Helpers

    /**
     * Calculate the discount amount for a given cart total.
     */
    public function calculateDiscount(float $cartTotal): float
    {
        return match ($this->type) {
            'percentage'    => round($cartTotal * ($this->value / 100), 2),
            'fixed'         => min($this->value, $cartTotal),
            'free_shipping' => 0.00, // handled separately in shipping logic
            default         => 0.00,
        };
    }
}