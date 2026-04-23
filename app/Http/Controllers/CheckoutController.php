<?php

namespace App\Http\Controllers;

use App\Jobs\ConsumeCouponJob;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CheckoutController
    |--------------------------------------------------------------------------
    | Handles the checkout page and order submission.
    | No authentication required — guest session ID used instead.
    |
    | Routes (defined in routes/web.php):
    |   GET   /checkout   → index()
    |   POST  /checkout   → store()
    */

    /**
     * GET /checkout
     *
     * Show the checkout page with the current cart.
     * Redirects to cart if cart is empty — nothing to check out.
     */
    public function index()
    {
        $cart = session('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Your cart is empty. Add some items before checking out.');
        }

        $total = collect($cart)->sum('price');

        return view('checkout.index', compact('cart', 'total'));
    }

    /**
     * POST /checkout
     *
     * Process the order:
     *   1. Validate form fields
     *   2. Dispatch ConsumeCouponJob if a coupon was applied
     *   3. Clear cart session
     *   4. Redirect to success page with order ID
     *
     * Form fields:
     *   first_name       string   required
     *   last_name        string   required
     *   email            string   required  email
     *   address          string   required
     *   city             string   required
     *   postcode         string   required
     *   coupon_id        integer  optional  — filled by JS after coupon reserved
     *   coupon_key  string   optional  — filled by JS after coupon reserved
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['required', 'string', 'max:100'],
            'email'           => ['required', 'email', 'max:255'],
            'address'         => ['required', 'string', 'max:255'],
            'city'            => ['required', 'string', 'max:100'],
            'postcode'        => ['required', 'string', 'max:20'],
            'coupon_id'       => ['sometimes', 'integer'],
            'coupon_key' => ['sometimes', 'string'],
        ]);

        // Generate a simple order ID.
        // Replace with a real orders table insert in production.
        $orderId = rand(100000, 999999);

        // ── Consume coupon if one was applied ─────────────────────────────────
        // coupon_id and coupon_key are hidden inputs filled by the JS
        // after the coupon status poll returns 'valid'.
        if ($request->filled('coupon_id') && $request->filled('coupon_key')) {
            ConsumeCouponJob::dispatch(
                couponId:       (int) $request->input('coupon_id'),
                userId:         $this->guestUserId(),
                orderId:        $orderId,
                couponKey:      $request->input('coupon_key'),
            )->onQueue('default');
        }

        // ── Clear cart and redirect ───────────────────────────────────────────
        session()->forget('cart');

        return redirect()->route('order.success')
            ->with('order_id', 'CS-' . $orderId);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Generate or retrieve a stable numeric guest user ID from the session.
     * Replaces auth()->id() — no login required.
     * Must return the same ID that CouponController::guestUserId() returns,
     * since both use the same session key: 'guest_user_id'.
     */
    private function guestUserId(): int
    {
        if (! session()->has('guest_user_id')) {
            session(['guest_user_id' => rand(100000, 999999)]);
        }

        return session('guest_user_id');
    }
}