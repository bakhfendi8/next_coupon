<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponSetting;
use App\Models\CouponUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CouponRuleEngine
{
    /**
     * Evaluate all rules against the LATEST active settings.
     * Always called fresh inside the job — never use a cached version.
     *
     * @return array{valid: bool, reason: string|null, setting_version: int}
     */
    public function evaluate(Coupon $coupon, int $userId, array $cart): array
    {
        // Always fetch the latest published settings at evaluation time.
        // This is intentional — rules may have changed since the job was dispatched.
        $setting = CouponSetting::where('coupon_id', $coupon->id)
            ->where('is_active', true)
            ->latest('version')
            ->firstOrFail();

        $rules = [
            'expired'          => fn() => $this->checkExpiry($coupon, $setting),
            'global_limit'     => fn() => $this->checkGlobalLimit($coupon, $setting),
            'per_user_limit'   => fn() => $this->checkPerUserLimit($coupon, $userId, $setting),
            'min_cart_value'   => fn() => $this->checkMinCartValue($cart, $setting),
            'first_time_user'  => fn() => $this->checkFirstTimeUser($userId, $setting),
            'category'         => fn() => $this->checkCategory($cart, $setting),
            'time_window'      => fn() => $this->checkTimeWindow($setting),
        ];

        foreach ($rules as $ruleName => $check) {
            $result = $check();
            if (! $result['passed']) {
                return [
                    'valid'           => false,
                    'reason'          => $result['reason'],
                    'rule_failed'     => $ruleName,
                    'setting_version' => $setting->version,
                ];
            }
        }

        return [
            'valid'           => true,
            'reason'          => null,
            'rule_failed'     => null,
            'setting_version' => $setting->version,
        ];
    }

    // -------------------------------------------------------------------------
    // Individual rule checks
    // -------------------------------------------------------------------------

    private function checkExpiry(Coupon $coupon, CouponSetting $setting): array
    {
        $expired = $setting->expires_at && now()->isAfter($setting->expires_at);
        return [
            'passed' => ! $expired,
            'reason' => $expired ? 'Coupon has expired.' : null,
        ];
    }

    private function checkGlobalLimit(Coupon $coupon, CouponSetting $setting): array
    {
        if (! $setting->global_usage_limit) {
            return ['passed' => true, 'reason' => null];
        }

        // Count only permanently consumed usages (not pending reservations).
        $used = CouponUsage::where('coupon_id', $coupon->id)
            ->where('status', 'consumed')
            ->count();

        $passed = $used < $setting->global_usage_limit;
        return [
            'passed' => $passed,
            'reason' => $passed ? null : 'Coupon usage limit reached.',
        ];
    }

    private function checkPerUserLimit(Coupon $coupon, int $userId, CouponSetting $setting): array
    {
        if (! $setting->per_user_limit) {
            return ['passed' => true, 'reason' => null];
        }

        $used = CouponUsage::where('coupon_id', $coupon->id)
            ->where('user_id', $userId)
            ->where('status', 'consumed')
            ->count();

        $passed = $used < $setting->per_user_limit;
        return [
            'passed' => $passed,
            'reason' => $passed ? null : 'You have already used this coupon the maximum number of times.',
        ];
    }

    private function checkMinCartValue(array $cart, CouponSetting $setting): array
    {
        if (! $setting->min_cart_value) {
            return ['passed' => true, 'reason' => null];
        }

        $passed = $cart['total'] >= $setting->min_cart_value;
        return [
            'passed' => $passed,
            'reason' => $passed ? null : "Minimum cart value of {$setting->min_cart_value} required.",
        ];
    }

    private function checkFirstTimeUser(int $userId, CouponSetting $setting): array
    {
        if (! $setting->first_time_user_only) {
            return ['passed' => true, 'reason' => null];
        }

        $hasOrders = DB::table('orders')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->exists();

        $passed = ! $hasOrders;
        return [
            'passed' => $passed,
            'reason' => $passed ? null : 'Coupon is valid for first-time customers only.',
        ];
    }

    private function checkCategory(array $cart, CouponSetting $setting): array
    {
        if (! $setting->allowed_categories) {
            return ['passed' => true, 'reason' => null];
        }

        $allowed   = $setting->allowed_categories; // array of category IDs
        $cartCats  = collect($cart['items'])->pluck('category_id')->unique()->toArray();
        $intersect = array_intersect($allowed, $cartCats);

        $passed = ! empty($intersect);
        return [
            'passed' => $passed,
            'reason' => $passed ? null : 'Your cart does not contain eligible product categories.',
        ];
    }

    private function checkTimeWindow(CouponSetting $setting): array
    {
        if (! $setting->active_from && ! $setting->active_until) {
            return ['passed' => true, 'reason' => null];
        }

        $now    = now();
        $after  = ! $setting->active_from  || $now->isAfter($setting->active_from);
        $before = ! $setting->active_until || $now->isBefore($setting->active_until);
        $passed = $after && $before;

        return [
            'passed' => $passed,
            'reason' => $passed ? null : 'Coupon is not active in the current time window.',
        ];
    }
}