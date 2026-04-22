{{-- resources/views/order/success.blade.php --}}
@extends('layouts.app')
@section('title', 'Order Confirmed — CouponSys')

@section('content')
<div class="min-h-[calc(100vh-64px)] flex flex-col items-center justify-center text-center px-6 py-20">

    {{-- Success icon --}}
    <div class="w-20 h-20 rounded-full bg-forest/10 flex items-center justify-center mb-6">
        <svg class="w-10 h-10 stroke-forest" viewBox="0 0 36 36" fill="none" stroke-width="2.5"
            stroke-linecap="round" stroke-linejoin="round">
            <circle cx="18" cy="18" r="16"/>
            <polyline points="10,18 16,24 26,12"/>
        </svg>
    </div>

    <h1 class="font-serif text-4xl tracking-tight mb-3">Order placed!</h1>

    <p class="text-ink-3 text-base max-w-sm leading-relaxed mb-4">
        Thank you for your order. You'll receive a confirmation email shortly.
    </p>

    @if(session('order_id'))
        <div class="font-mono text-sm text-ink-2 bg-paper-2 px-5 py-2 rounded-full mb-8">
            Order #{{ session('order_id') }}
        </div>
    @endif

    <a href="{{ route('home') }}"
        class="inline-block bg-ink text-paper font-medium text-sm px-6 py-3 rounded-xl
        hover:bg-ink/85 transition-colors">
        Continue shopping
    </a>
</div>
@endsection