<nav class="sticky top-0 z-50 bg-paper border-b border-ink/10 h-16 flex items-center justify-between px-6">
    <a href="{{ route('home') }}" class="font-serif text-2xl tracking-tight">
        Coupon<em class="text-accent not-italic">Sys</em>
    </a>

    <div class="flex items-center gap-5">
        @auth
            <a href="{{ route('cart.index') }}" class="text-sm text-ink-2 hover:text-ink transition-colors flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                </svg>
                Cart
            </a>
            <span class="text-ink/20">|</span>
            <span class="text-sm text-ink-2">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="text-sm text-ink-3 hover:text-ink transition-colors bg-transparent border-none cursor-pointer p-0">
                    Logout
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="text-sm text-ink-2 hover:text-ink transition-colors">Login</a>
            <a href="{{ route('checkout.index') }}" class="text-sm bg-ink text-paper px-4 py-2 rounded-lg hover:bg-ink/85 transition-colors">
                Checkout
            </a>
        @endauth
    </div>
</nav>