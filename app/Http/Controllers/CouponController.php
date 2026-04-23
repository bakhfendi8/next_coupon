<?php

namespace App\Http\Controllers;

use App\Jobs\ConsumeCouponJob;
use App\Jobs\ReleaseCouponJob;
use App\Jobs\ValidateCouponJob;
use App\Models\Coupon;
use App\Models\CouponEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CouponController
    |--------------------------------------------------------------------------
    | Handles all coupon-related API endpoints.
    | No authentication required — guest session ID used as user identifier.
    |
    | Routes (defined in routes/api.php):
    |   POST /api/apply-coupon          → apply()
    |   GET  /api/coupon-status         → status()
    |   POST /api/checkout/complete     → checkoutSuccess()
    |   POST /api/checkout/fail         → checkoutFail()
    */

    /**
     * POST /api/apply-coupon
     *
     * Validate the request, dispatch ValidateCouponJob to the high queue,
     * and return HTTP 202 immediately — the user does not wait for validation.
     *
     * Request:
     *   coupon_code  string  required
     *   cart.total   number  required
     *   cart.items   array   required
     *
     * Response 202:
     *   { "status": "processing", "coupon_key": "..." }
     *
     * Response 404:
     *   { "status": "error", "message": "Coupon not found." }
     */
    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coupon_code'              => ['required', 'string', 'max:50'],
            'cart'                     => ['required', 'array'],
            'cart.total'               => ['required', 'numeric', 'min:0'],
            'cart.items'               => ['required', 'array', 'min:1'],
            'cart.items.*.id'          => ['sometimes', 'integer'],
            'cart.items.*.name'        => ['sometimes', 'string'],
            'cart.items.*.price'       => ['sometimes', 'numeric'],
            'cart.items.*.category_id' => ['sometimes', 'integer'],
        ]);

        $coupon = Coupon::where('code', strtoupper($data['coupon_code']))->first();

        if (! $coupon) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Coupon not found.',
            ], 404);
        }

        $userId         = $this->guestUserId();
        $cartHash       = md5(json_encode($data['cart']));
        $couponKey = "{$userId}:{$coupon->id}:{$cartHash}";

        ValidateCouponJob::dispatch(
            couponId:       $coupon->id,
            userId:         $userId,
            cart:           $data['cart'],
            couponKey: $couponKey,
        )->onQueue('high');

        return response()->json([
            'status'          => 'processing',
            'message'         => 'Coupon verification in progress.',
            'coupon_key' => $couponKey,
        ], 202);
    }

    /**
     * GET /api/coupon-status?coupon_key=xxx
     *
     * Poll this endpoint every 500-600ms after calling apply().
     * Returns the job result once ValidateCouponJob completes.
     *
     * Response — still processing:
     *   { "status": "pending" }
     *
     * Response — coupon valid and reserved:
     *   { "status": "valid", "reserved": true, "coupon": {...}, "setting_version": 1 }
     *
     * Response — coupon invalid:
     *   { "status": "invalid", "reason": "Coupon has expired." }
     */
    public function status(Request $request): JsonResponse
    {
        $request->validate([
            'coupon_key' => ['required', 'string'],
        ]);

        $key = $request->input('coupon_key');

        $event = CouponEvent::where('coupon_key', 'LIKE', $key . '%')
            ->whereIn('event', ['reserved', 'validation_failed', 'reservation_failed'])
            ->latest('occurred_at')
            ->first();

        if (! $event) {
            return response()->json(['status' => 'pending']);
        }

        if ($event->event === 'reserved') {
            $payload = is_array($event->payload) ? $event->payload : json_decode($event->payload, true);
            return response()->json([
                'status'          => 'valid',
                'reserved'        => true,
                'setting_version' => $payload['setting_version'] ?? null,
                'coupon'          => Coupon::find($event->coupon_id),
            ]);
        }

        $payload = is_array($event->payload) ? $event->payload : json_decode($event->payload, true);
        return response()->json([
            'status' => 'invalid',
            'reason' => $payload['reason'] ?? 'Coupon could not be applied.',
        ]);
    }

    /**
     * POST /api/checkout/complete
     *
     * Called after a successful order placement.
     * Dispatches ConsumeCouponJob to permanently write usage to MySQL
     * and release the Redis reservation.
     *
     * Request:
     *   coupon_id        integer  required
     *   order_id         integer  required
     *   coupon_key       string   required
     *
     * Response 202:
     *   { "status": "consuming", "message": "Coupon consumption queued." }
     */
    public function checkoutSuccess(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coupon_id'       => ['required', 'integer'],
            'order_id'        => ['required', 'integer'],
            'coupon_key'      => ['required', 'string'],
        ]);

        ConsumeCouponJob::dispatch(
            couponId:       $data['coupon_id'],
            userId:         $this->guestUserId(),
            orderId:        $data['order_id'],
            couponKey:      $data['coupon_key'],
        )->onQueue('default');

        return response()->json([
            'status'  => 'consuming',
            'message' => 'Coupon consumption queued.',
        ], 202);
    }

    /**
     * POST /api/checkout/fail
     *
     * Called when checkout fails or the user cancels.
     * Dispatches ReleaseCouponJob to free the Redis reservation
     * so other users can claim the coupon.
     *
     * Request:
     *   coupon_id        integer  required
     *   coupon_key  string   required
     *   reason           string   optional  (e.g. "payment_failed")
     *
     * Response 202:
     *   { "status": "releasing", "message": "Coupon reservation released." }
     */
    public function checkoutFail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coupon_id'       => ['required', 'integer'],
            'coupon_key'      => ['required', 'string'],
            'reason'          => ['sometimes', 'string', 'max:100'],
        ]);

        ReleaseCouponJob::dispatch(
            couponId:       $data['coupon_id'],
            userId:         $this->guestUserId(),
            couponKey:      $data['coupon_key'],
            reason:         $data['reason'] ?? 'checkout_failed',
        )->onQueue('default');

        return response()->json([
            'status'  => 'releasing',
            'message' => 'Coupon reservation released.',
        ], 202);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Generate or retrieve a stable numeric guest user ID from the session.
     * Replaces auth()->id() — no login required.
     * Persists for the duration of the browser session.
     */
    private function guestUserId(): int
    {
        if (! session()->has('guest_user_id')) {
            session(['guest_user_id' => rand(100000, 999999)]);
        }

        return session('guest_user_id');
    }
}