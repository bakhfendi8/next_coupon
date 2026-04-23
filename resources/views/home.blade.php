@extends('layouts.app')
@section('title', 'CouponSys — Home')

@section('content')

{{-- Hero --}}
<section class="max-w-5xl mx-auto px-6 pt-16 pb-10 text-center">
    <h1 class="font-serif text-5xl tracking-tight text-ink mb-3">
        Quality goods,<br><em class="text-accent not-italic">great prices.</em>
    </h1>
    <p class="text-ink-3 text-base max-w-md mx-auto">
        Free shipping on all orders. Apply a coupon at checkout.
    </p>
</section>

{{-- Product grid --}}
<section class="max-w-5xl mx-auto px-6 pb-20">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

        {{-- Product card --}}
        @php
            $products = [
                [
                    'name'     => 'Running Shoes Nimbus',
                    'price'    => 159,
                    'category' => 'Footwear',
                    'badge'    => 'Bestseller',
                    'icon'     => 'emoji-running-shoe',
                    'iconBg'   => 'bg-amber-50',
                    'iconColor'=> 'text-amber-500',
                ],
                [
                    'name'     => 'Clutch Bag',
                    'price'    => 88,
                    'category' => 'Bags',
                    'badge'    => null,
                    'icon'     => 'emoji-clutch-bag',
                    'iconBg'   => 'bg-blue-50',
                    'iconColor'=> 'text-blue-500',
                ],
                [
                    'name'     => 'Headphone',
                    'price'    => 65,
                    'category' => 'Accessories',
                    'badge'    => 'New',
                    'icon'     => 'emoji-headphone',
                    'iconBg'   => 'bg-yellow-50',
                    'iconColor'=> 'text-yellow-500',
                ],
                [
                    'name'     => 'Kick Scooter',
                    'price'    => 34,
                    'category' => 'Accessories',
                    'badge'    => null,
                    'icon'     => 'emoji-kick-scooter',
                    'iconBg'   => 'bg-purple-50',
                    'iconColor'=> 'text-purple-400',
                ],
                [
                    'name'     => '100% Cotton T-shirt',
                    'price'    => 29,
                    'category' => 'Clothing',
                    'badge'    => null,
                    'icon'     => 'emoji-t-shirt',
                    'iconBg'   => 'bg-rose-50',
                    'iconColor'=> 'text-rose-400',
                ],
                [
                    'name'     => 'Wool Socks',
                    'price'    => 18,
                    'category' => 'Clothing',
                    'badge'    => 'New',
                    'icon'     => 'emoji-socks',
                    'iconBg'   => 'bg-orange-50',
                    'iconColor'=> 'text-orange-400',
                ],
    ];
        @endphp

        @foreach($products as $p)
            <div class="bg-paper border border-ink/10 rounded-2xl overflow-hidden group hover:border-ink/25 hover:shadow-sm transition-all">
 
                {{-- Product image area with icon --}}
                <div class="h-44 {{ $p['iconBg'] }} flex flex-col items-center justify-center gap-3 relative">
                    <x-dynamic-component :component="$p['icon']" class="w-16 h-16 {{ $p['iconColor'] }} transition-transform group-hover:scale-110 duration-300" />
 
                    {{-- Badge --}}
                    @if($p['badge'])
                        <span class="absolute top-3 right-3 font-mono text-[10px] bg-accent text-white px-2.5 py-1 rounded-full">
                            {{ $p['badge'] }}
                        </span>
                    @endif
                </div>
 
                {{-- Product info --}}
                <div class="p-5">
                    <p class="font-mono text-[10px] text-ink-3 uppercase tracking-widest mb-1">
                        {{ $p['category'] }}
                    </p>
                    <p class="font-medium text-sm leading-snug mb-3">{{ $p['name'] }}</p>
    
                    <div class="flex items-center justify-between">
                        <span class="font-mono font-medium text-sm">RM {{ $p['price'] }}.00</span>
    
                        <form method="POST" action="{{ route('cart.add') }}">
                            @csrf
                            <input type="hidden" name="product" value="{{ $p['name'] }}">
                            <input type="hidden" name="price" value="{{ $p['price'] }}">
                            <input type="hidden" name="category" value="{{ $p['category'] }}">
                            <button type="submit" class="flex items-center gap-1.5 text-xs bg-ink text-paper
                                px-3 py-1.5 rounded-lg hover:bg-ink/85 transition-colors font-medium">
                                <x-heroicon-o-plus class="w-3.5 h-3.5" />
                                Add to cart
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

    </div>
</section>

@endsection