<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class CartController extends Controller
{
    public function index()
    {
        $cart = session('cart', []);
        return view('cart.index', compact('cart'));
    }

    public function add(Request $request): RedirectResponse
    {
        $cart = session('cart', []);

        $cart[] = [
            'name'  => $request->input('product'),
            'price' => (float) $request->input('price'),
        ];

        session(['cart' => $cart]);

        return redirect()->route('cart.index')
            ->with('success', $request->input('product') . ' added to cart.');
    }

    public function remove(Request $request): RedirectResponse
    {
        $cart  = session('cart', []);
        $index = (int) $request->input('index');

        unset($cart[$index]);
        session(['cart' => array_values($cart)]);

        return redirect()->route('cart.index');
    }

    public function clear(): RedirectResponse
    {
        session()->forget('cart');
        return redirect()->route('cart.index');
    }
}