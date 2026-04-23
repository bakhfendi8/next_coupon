<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CartController
    |--------------------------------------------------------------------------
    | Session-based cart — no database, no auth required.
    | Cart is stored in the PHP session as a plain array.
    |
    | Routes (defined in routes/web.php):
    |   GET    /cart             → index()
    |   POST   /cart/add         → add()
    |   POST   /cart/remove      → remove()
    |   POST   /cart/clear       → clear()
    */

    /**
     * GET /cart
     *
     * Display cart contents. Passes cart array and computed total to the view.
     */
    public function index()
    {
        $cart  = session('cart', []);
        $total = collect($cart)->sum('price');

        return view('cart.index', compact('cart', 'total'));
    }

    /**
     * POST /cart/add
     *
     * Add a product to the cart session.
     * Called from the home page product cards via a form POST.
     *
     * Form fields:
     *   product      string  required  — product name
     *   price        number  required  — unit price
     *   category     string  optional  — category label
     *   category_id  int     optional  — category ID for coupon rule matching
     *   emoji        string  optional  — display emoji fallback
     */
    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product'     => ['required', 'string', 'max:255'],
            'price'       => ['required', 'numeric', 'min:0'],
            'category'    => ['sometimes', 'string'],
            'category_id' => ['sometimes', 'integer'],
            'emoji'       => ['sometimes', 'string'],
        ]);

        $cart   = session('cart', []);
        $cart[] = [
            'name'        => $data['product'],
            'price'       => (float) $data['price'],
            'category'    => $data['category']    ?? 'General',
            'category_id' => $data['category_id'] ?? 1,
            'emoji'       => $data['emoji']        ?? '📦',
            'qty'         => 1,
        ];

        session(['cart' => $cart]);

        return redirect()->route('cart.index')
            ->with('success', $data['product'] . ' added to cart.');
    }

    /**
     * POST /cart/remove
     *
     * Remove a single item by its array index.
     * Index is 0-based, matching the order items appear in the cart view.
     *
     * Form fields:
     *   index  int  required — position of item to remove
     */
    public function remove(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
        ]);

        $cart  = session('cart', []);
        $index = $data['index'];

        if (isset($cart[$index])) {
            unset($cart[$index]);
            session(['cart' => array_values($cart)]); // re-index
        }

        return redirect()->route('cart.index');
    }

    /**
     * POST /cart/clear
     *
     * Remove all items from the cart session.
     * Called after a successful order or by user action.
     */
    public function clear(): RedirectResponse
    {
        session()->forget('cart');

        return redirect()->route('cart.index')
            ->with('success', 'Cart cleared.');
    }
}