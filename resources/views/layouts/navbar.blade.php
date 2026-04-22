<nav class="sticky top-0 z-50 bg-paper border-b border-ink/10 h-16 flex items-center justify-between px-6">
    <a href="{{ route('home') }}" class="font-serif text-2xl tracking-tight">
        Coupon<em class="text-accent not-italic">Sys</em>
    </a>

    <div class="flex items-center gap-5">
        <a href="{{ route('home') }}" class="text-sm text-ink-2 hover:text-ink transition-colors">Shop</a>
        <a href="{{ route('cart.index') }}" class="text-sm text-ink-2 hover:text-ink transition-colors flex items-center gap-1.5">
            <x-heroicon-o-shopping-bag class="w-5 h-5" />
            Cart
            @php $cartCount = count(session('cart', [])); @endphp
            @if($cartCount > 0)
                <span class="bg-accent text-white text-[10px] font-mono w-4 h-4 rounded-full flex items-center justify-center">
                {{ $cartCount }}
                </span>
            @endif
        </a>
        <a href="{{ route('checkout.index') }}"
        class="text-sm bg-ink text-paper px-4 py-2 rounded-lg hover:bg-ink/85 transition-colors font-medium">
            Checkout
        </a>
    </div>
</nav>