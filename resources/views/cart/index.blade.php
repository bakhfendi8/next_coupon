@extends('layouts.app')
@section('title', 'Your Cart — CouponSys')

@section('content')

<div class="max-w-3xl mx-auto px-6 py-12">

    <h1 class="font-serif text-3xl tracking-tight mb-8">Your cart</h1>

    @if(empty($cart))
        <div class="text-center py-20 text-ink-3">
            <div class="text-5xl mb-4"></div>
            <p class="text-base mb-4">Your cart is empty.</p>
            <a href="{{ route('home') }}"
                class="inline-block bg-ink text-paper text-sm font-medium px-5 py-2.5 rounded-xl hover:bg-ink/85 transition-colors">
                Continue shopping
            </a>
        </div>
    @else

        {{-- Cart items --}}
        <div class="divide-y divide-ink/10 mb-8">
            @foreach($cart as $i => $item)
                <div class="flex items-center gap-4 py-4">
                    <div class="flex-1">
                        <p class="text-sm font-medium">{{ $item['name'] }}</p>
                        <p class="font-mono text-xs text-ink-3 mt-0.5">Qty: 1</p>
                    </div>
                    <p class="font-mono text-sm font-medium">RM {{ $item['price'] }}.00</p>
                    <form method="POST" action="{{ route('cart.remove') }}">
                        @csrf
                        <input type="hidden" name="index" value="{{ $i }}">
                        <button type="submit"
                            class="text-ink-3 hover:text-accent transition-colors text-xs font-mono underline underline-offset-2 bg-transparent border-none cursor-pointer">
                            remove
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        {{-- Summary --}}
        <div class="bg-paper-2 rounded-2xl p-6">
            @php $total = collect($cart)->sum('price'); @endphp
            <div class="flex justify-between text-sm text-ink-2 mb-2">
                <span>Subtotal ({{ count($cart) }} items)</span>
                <span class="font-mono">RM {{ $total }}.00</span>
            </div>
            <div class="flex justify-between text-sm mb-4">
                <span class="text-ink-2">Shipping</span>
                <span class="font-mono text-forest">Free</span>
            </div>
            <div class="flex justify-between font-semibold text-base border-t border-ink/15 pt-4 mb-5">
                <span>Total</span>
                <span class="font-mono">RM {{ $total }}.00</span>
            </div>
            <a href="{{ route('checkout.index') }}"
                class="block w-full text-center bg-accent text-white font-semibold text-sm py-3.5 rounded-xl hover:bg-accent-hover transition-colors">
                Proceed to checkout
            </a>
        </div>

    @endif
</div>

@endsection