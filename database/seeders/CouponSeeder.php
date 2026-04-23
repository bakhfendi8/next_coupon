<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\CouponSetting;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    /*
    |--------------------------------------------------------------------------
    | CouponSeeder
    |--------------------------------------------------------------------------
    | Seeds the coupons and coupon_settings tables with test data.
    |
    | Run via: php artisan db:seed --class=CouponSeeder
    | Or via:  php artisan db:seed  (if registered in DatabaseSeeder)
    |
    | Test coupon codes:
    |   SAVE10    → 10% off, no restrictions
    |   FIRST50   → RM50 fixed off, min cart RM 100
    |   FREESHIP  → free shipping type, no cart minimum
    |   NEWUSER   → 15% off, first-time users only
    |   CATEGORY1 → 20% off, category ID 1 only (Footwear)
    |   EXPIRED   → already expired (for testing invalid path)
    |   MAXED     → global limit of 0 (for testing limit reached)
    */

    public function run(): void
    {
        $this->command->info('Seeding coupons...');

        // ── 1. SAVE10 — 10% off, no restrictions ──
        $coupon = Coupon::firstOrCreate(
            ['code' => 'SAVE10'],
            ['type' => 'percentage', 'value' => 10]
        );
        CouponSetting::firstOrCreate(
            ['coupon_id' => $coupon->id, 'version' => 1],
            [
                'is_active'          => true,
                'global_usage_limit' => 100,
                'per_user_limit'     => 3,
                'min_cart_value'     => null,
                'first_time_user_only' => false,
                'allowed_categories' => null,
                'active_from'        => null,
                'active_until'       => null,
                'expires_at'         => now()->addYear(),
            ]
        );

        // ── 2. FIRST50 — RM50 fixed off, min cart RM100 ──
        $coupon = Coupon::firstOrCreate(
            ['code' => 'FIRST50'],
            ['type' => 'fixed', 'value' => 50]
        );
        CouponSetting::firstOrCreate(
            ['coupon_id' => $coupon->id, 'version' => 1],
            [
                'is_active'          => true,
                'global_usage_limit' => 200,
                'per_user_limit'     => 1,
                'min_cart_value'     => 100.00,
                'first_time_user_only' => false,
                'allowed_categories' => null,
                'active_from'        => null,
                'active_until'       => null,
                'expires_at'         => now()->addYear(),
            ]
        );

        // ── 3. FREESHIP — free shipping, no minimum ──
        $coupon = Coupon::firstOrCreate(
            ['code' => 'FREESHIP'],
            ['type' => 'free_shipping', 'value' => 0]
        );
        CouponSetting::firstOrCreate(
            ['coupon_id' => $coupon->id, 'version' => 1],
            [
                'is_active'          => true,
                'global_usage_limit' => null,  // unlimited
                'per_user_limit'     => 1,
                'min_cart_value'     => null,
                'first_time_user_only' => false,
                'allowed_categories' => null,
                'active_from'        => null,
                'active_until'       => null,
                'expires_at'         => now()->addMonths(6),
            ]
        );

        // ── 4. NEWUSER — 15% off, first-time users only ───
        $coupon = Coupon::firstOrCreate(
            ['code' => 'NEWUSER'],
            ['type' => 'percentage', 'value' => 15]
        );
        CouponSetting::firstOrCreate(
            ['coupon_id' => $coupon->id, 'version' => 1],
            [
                'is_active'            => true,
                'global_usage_limit'   => 500,
                'per_user_limit'       => 1,
                'min_cart_value'       => null,
                'first_time_user_only' => true,
                'allowed_categories'   => null,
                'active_from'          => null,
                'active_until'         => null,
                'expires_at'           => now()->addYear(),
            ]
        );

        // ── 5. CATEGORY1 — 20% off, Footwear only (category_id = 1) ──
        $coupon = Coupon::firstOrCreate(
            ['code' => 'CATEGORY1'],
            ['type' => 'percentage', 'value' => 20]
        );
        CouponSetting::firstOrCreate(
            ['coupon_id' => $coupon->id, 'version' => 1],
            [
                'is_active'            => true,
                'global_usage_limit'   => 50,
                'per_user_limit'       => 2,
                'min_cart_value'       => null,
                'first_time_user_only' => false,
                'allowed_categories'   => [1],  // category_id 1 = Footwear
                'active_from'          => null,
                'active_until'         => null,
                'expires_at'           => now()->addYear(),
            ]
        );

        // ── 6. EXPIRED — expired coupon (tests invalid path) ───
        $coupon = Coupon::firstOrCreate(
            ['code' => 'EXPIRED'],
            ['type' => 'percentage', 'value' => 5]
        );
        CouponSetting::firstOrCreate(
            ['coupon_id' => $coupon->id, 'version' => 1],
            [
                'is_active'          => true,
                'global_usage_limit' => 100,
                'per_user_limit'     => 1,
                'min_cart_value'     => null,
                'first_time_user_only' => false,
                'allowed_categories' => null,
                'active_from'        => null,
                'active_until'       => null,
                'expires_at'         => now()->subDay(), // already expired
            ]
        );

        // ── 7. MAXED — global limit reached (tests limit path) ──
        $coupon = Coupon::firstOrCreate(
            ['code' => 'MAXED'],
            ['type' => 'percentage', 'value' => 25]
        );
        CouponSetting::firstOrCreate(
            ['coupon_id' => $coupon->id, 'version' => 1],
            [
                'is_active'          => true,
                'global_usage_limit' => 0,  // limit of 0 = immediately maxed
                'per_user_limit'     => 1,
                'min_cart_value'     => null,
                'first_time_user_only' => false,
                'allowed_categories' => null,
                'active_from'        => null,
                'active_until'       => null,
                'expires_at'         => now()->addYear(),
            ]
        );

        $this->command->info('✓ Seeded 7 coupons with settings.');
        $this->command->table(
            ['Code', 'Type', 'Value', 'Notes'],
            [
                ['SAVE10',    'percentage',   '10%',     'No restrictions'],
                ['FIRST50',   'fixed',        'RM50',    'Min cart RM100'],
                ['FREESHIP',  'free_shipping','RM0',     'Free shipping'],
                ['NEWUSER',   'percentage',   '15%',     'First-time users only'],
                ['CATEGORY1', 'percentage',   '20%',     'Footwear category only'],
                ['EXPIRED',   'percentage',   '5%',      'Already expired — invalid'],
                ['MAXED',     'percentage',   '25%',     'Limit 0 — invalid'],
            ]
        );
    }
}