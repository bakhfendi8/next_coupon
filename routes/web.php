<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — Coupon System
|--------------------------------------------------------------------------
| No auth middleware — all routes are public.
|
| Pages:
|   /                → home (product listing)
|   /cart            → cart page
|   /checkout        → checkout page with coupon input
|   /order/success   → order confirmation page
|
| Cart actions (form POST from Blade views):
|   POST /cart/add    → add item to session cart
|   POST /cart/remove → remove item by index
|   POST /cart/clear  → empty the entire cart
*/
 
// ── Pages
Route::get('/', fn () => view('home'))->name('home');
 
Route::get('/order/success', fn () => view('order.success'))->name('order.success');
 
// ── Cart
Route::get('/cart',          [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/add',     [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/remove',  [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/clear',   [CartController::class, 'clear'])->name('cart.clear');
 
// ── Checkout
Route::get('/checkout',  [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

