<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.index');
        }

        return view('checkout.index', compact('cart'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string'],
            'last_name'  => ['required', 'string'],
            'email'      => ['required', 'email'],
            'address'    => ['required', 'string'],
            'city'       => ['required', 'string'],
            'postcode'   => ['required', 'string'],
        ]);

        // Consume coupon if one was applied
        if ($request->filled('coupon_id') && $request->filled('coupon_key')) {
            \App\Jobs\ConsumeCouponJob::dispatch(
                couponId: (int) $request->input('coupon_id'),
                userId: 1, // Guest user — no auth required
                orderId: rand(100000, 999999),
                couponKey: $request->input('coupon_key'),
            )->onQueue('default');
        }

        $orderId = 'CS-'.rand(100000, 999999);
        session()->forget('cart');

        return redirect()->route('order.success')
            ->with('order_id', $orderId);
    }
}