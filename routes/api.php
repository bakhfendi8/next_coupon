<?php

/*
|--------------------------------------------------------------------------
| API Routes — Coupon System
|--------------------------------------------------------------------------
| Base URL: http://localhost:8000/api
|
| All endpoints are public — no auth middleware.
| Guest session ID is used as the user identifier instead.
|
| Endpoints:
|   GET  /api/ping                  → health check
|   POST /api/apply-coupon          → dispatch ValidateCouponJob (202)
|   GET  /api/coupon-status         → poll job result
|   POST /api/checkout/complete     → dispatch ConsumeCouponJob (202)
|   POST /api/checkout/fail         → dispatch ReleaseCouponJob (202)
*/

use App\Http\Controllers\CouponController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'status'    => 'ok',
        'timestamp' => now()->toISOString(),
        'queue'     => config('queue.default'),
    ]);
});

Route::post('/apply-coupon',      [CouponController::class, 'apply'])->name('coupon.apply');
Route::get('/coupon-status',      [CouponController::class, 'status'])->name('coupon.status');
Route::post('/checkout/complete', [CouponController::class, 'checkoutSuccess'])->name('coupon.checkout.complete');
Route::post('/checkout/fail',     [CouponController::class, 'checkoutFail'])->name('coupon.checkout.fail');