<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Prunable;

class CouponSetting extends Model
{
    use Prunable;

    protected $fillable = [
        'coupon_id',
        'version',
        'is_active',
        'global_usage_limit',
        'per_user_limit',
        'min_cart_value',
        'first_time_user_only',
        'allowed_categories',
        'active_from',
        'active_until',
        'expires_at',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'first_time_user_only' => 'boolean',
        'allowed_categories'   => 'array',        // JSON column — auto encode/decode
        'min_cart_value'       => 'decimal:2',
        'active_from'          => 'datetime',
        'active_until'         => 'datetime',
        'expires_at'           => 'datetime',
        'global_usage_limit'   => 'integer',
        'per_user_limit'       => 'integer',
        'version'              => 'integer',
    ];

    // ── Relationships

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    // ── Scopes

    /**
     * Only return active settings.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Latest version first.
     */
    public function scopeLatestVersion($query)
    {
        return $query->orderByDesc('version');
    }

    // ── Prunable

    /**
     * Prune old inactive settings older than 90 days.
     * Run via: php artisan model:prune
     */
    public function prunable()
    {
        return static::where('is_active', false)
            ->where('updated_at', '<', now()->subDays(90));
    }

    // ── Helpers

    /**
     * Check if this setting is currently within its active time window.
     */
    public function isWithinTimeWindow(): bool
    {
        $now = now();

        if ($this->active_from && $now->isBefore($this->active_from)) {
            return false;
        }

        if ($this->active_until && $now->isAfter($this->active_until)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the coupon is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }
}