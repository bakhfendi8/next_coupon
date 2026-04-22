@extends('layouts.app')
@section('title', 'Checkout — CouponSys')

@section('content')

    @php $total = collect($cart)->sum('price'); @endphp

    <div class="max-w-[1100px] mx-auto grid grid-cols-1 lg:grid-cols-[1fr_400px] min-h-[calc(100vh-64px)]">

        {{-- ── Left col: form ── --}}
        <div class="px-6 py-10 lg:px-12 border-b lg:border-b-0 lg:border-r border-ink/10">

            {{-- Progress --}}
            <div class="flex gap-1 mb-8">
                <div class="h-[3px] flex-1 rounded-full bg-forest"></div>
                <div class="h-[3px] flex-1 rounded-full bg-accent"></div>
                <div class="h-[3px] flex-1 rounded-full bg-ink/15"></div>
            </div>  

            <form id="checkoutForm" method="POST" action="{{ route('checkout.store') }}">
                @csrf

                {{-- 01 Contact --}}
                <p class="font-mono text-[11px] tracking-widest uppercase text-ink-3 mb-4">01 — Contact</p>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-ink-2 mb-1.5">First name</label>
                        <input type="text" name="first_name" value=""
                        class="w-full px-3.5 py-2.5 border border-ink/18 rounded-lg bg-paper text-sm 
                        focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/10 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-2 mb-1.5">Last name</label>
                        <input type="text" name="last_name"
                        class="w-full px-3.5 py-2.5 border border-ink/18 rounded-lg bg-paper text-sm
                        focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/10 transition-all">
                    </div>
                </div>
                <div class="mb-8">
                    <label class="block text-xs font-medium text-ink-2 mb-1.5">Email</label>
                    <input type="email" name="email" value=""
                    class="w-full px-3.5 py-2.5 border border-ink/18 rounded-lg bg-paper text-sm
                    focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/10 transition-all">
                </div>

                {{-- 02 Shipping --}}
                <p class="font-mono text-[11px] tracking-widest uppercase text-ink-3 mb-4">02 — Shipping</p>
                <div class="mb-4">
                    <label class="block text-xs font-medium text-ink-2 mb-1.5">Address</label>
                    <input type="text" name="address"
                    class="w-full px-3.5 py-2.5 border border-ink/18 rounded-lg bg-paper text-sm
                    focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/10 transition-all">
                </div>
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <div>
                        <label class="block text-xs font-medium text-ink-2 mb-1.5">City</label>
                        <input type="text" name="city"
                        class="w-full px-3.5 py-2.5 border border-ink/18 rounded-lg bg-paper text-sm
                        focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/10 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-2 mb-1.5">Postcode</label>
                        <input type="text" name="postcode"
                        class="w-full px-3.5 py-2.5 border border-ink/18 rounded-lg bg-paper text-sm
                        focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/10 transition-all">
                    </div>
                </div>

                {{-- 03 Coupon --}}
                <p class="font-mono text-[11px] tracking-widest uppercase text-ink-3 mb-4">03 — Coupon code</p>
                @include('partials.couponbox')

                {{-- Hidden coupon fields (filled by JS) --}}
                <input type="hidden" id="couponIdInput"        name="coupon_id">
                <input type="hidden" id="couponKeyInput"  name="coupon_key">

                {{-- 04 Payment --}}
                <p class="font-mono text-[11px] tracking-widest uppercase text-ink-3 mb-4 mt-8">04 — Payment</p>
                <div class="mb-4">
                    <label class="block text-xs font-medium text-ink-2 mb-1.5">Card number</label>
                    <input type="text" name="card_number" placeholder="4242 4242 4242 4242"
                    class="w-full px-3.5 py-2.5 border border-ink/18 rounded-lg bg-paper text-sm font-mono
                    focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/10 transition-all">
                </div>
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <div>
                        <label class="block text-xs font-medium text-ink-2 mb-1.5">Expiry</label>
                        <input type="text" name="expiry" placeholder="MM/YY"
                        class="w-full px-3.5 py-2.5 border border-ink/18 rounded-lg bg-paper text-sm font-mono
                        focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/10 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-2 mb-1.5">CVV</label>
                        <input type="text" name="cvv" placeholder="123"
                        class="w-full px-3.5 py-2.5 border border-ink/18 rounded-lg bg-paper text-sm font-mono
                        focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/10 transition-all">
                    </div>
                </div>

                {{-- Place order --}}
                <button type="submit" id="placeBtn"
                    class="w-full py-4 bg-accent text-white text-base font-semibold rounded-xl tracking-tight
                    hover:bg-accent-hover active:scale-[.99] transition-all
                    disabled:bg-paper-3 disabled:text-ink-3 disabled:cursor-not-allowed">
                    Place order — RM <span id="placeTotal">{{ $total }}.00</span>
                </button>
                <p class="text-xs text-ink-3 text-center mt-3">Secured by 256-bit SSL · No card details stored</p>

            </form>
        </div>

        {{-- ── Right col: order summary ── --}}
        <div class="px-6 py-10 bg-paper-2">

            <p class="font-mono text-[11px] tracking-widest uppercase text-ink-3 mb-5">Order summary</p>

            {{-- Cart items --}}
            <div class="divide-y divide-ink/10 mb-5">
                @foreach($cart as $item)
                    <div class="flex items-start gap-3 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium leading-snug">{{ $item['name'] }}</p>
                            <p class="font-mono text-xs text-ink-3">Qty 1</p>
                        </div>
                        <p class="font-mono text-sm font-medium flex-shrink-0">RM {{ $item['price'] }}.00</p>
                    </div>
                @endforeach
            </div>

            {{-- Totals --}}
            <div class="space-y-1 mb-6">
                <div class="flex justify-between text-sm text-ink-2 py-1">
                    <span>SubTotal</span>
                    <span class="font-mono">RM {{ $total }}.00</span>
                </div>
                <div class="flex justify-between text-sm py-1">
                    <span class="text-ink-2">Shipping</span>
                    <span class="font-mono text-forest">Free</span>
                </div>
                {{-- Discount row (shown by JS when coupon applied) --}}
                <div id="discountRow" class="hidden justify-between text-sm text-forest py-1">
                    <span class="flex items-center gap-1.5">
                        Discount
                        <span id="discountBadge" class="font-mono text-[10px] bg-forest/10 px-2 py-0.5 rounded-full"></span>
                    </span>
                    <span id="discountAmount" class="font-mono text-forest"></span>
                </div>
                <div class="flex justify-between font-semibold text-base border-t border-ink/15 pt-3 mt-1">
                    <span>Total</span>
                    <span id="totalAmount" class="font-mono">RM {{ $total }}.00</span>
                </div>
            </div>

            {{-- Event log --}}
            <div>
                <p class="font-mono text-[11px] tracking-widest uppercase text-ink-3 mb-3">Event log</p>
                <div id="eventLog" class="flex flex-col gap-1.5 min-h-[40px]">
                    <p data-empty class="font-mono text-xs text-ink-3">No events yet.</p>
                </div>
            </div>

        </div>
    </div>

@endsection

@push('scripts')
<script>
const SubTotal = {{ $total }};
const token    = document.querySelector('meta[name="csrf-token"]').content;
let appliedCoupon   = null;
let couponKey  = null;

// ── CouponUI namespace ────────────────────────────────────────────────────
const CouponUI = {
    fill(code) {
        document.getElementById('couponInput').value = code;
        document.getElementById('couponInput').focus();
    },

    async apply() {
        const code = document.getElementById('couponInput').value.trim().toUpperCase();
        if (!code) { window.toast('Enter a coupon code first.', 'error'); return; }

        this._setLoading(true);
        this._setStatus('processing', 'Verifying coupon…');
        logEvent('dispatched', `ValidateCouponJob dispatched for ${code}`);

        try {
            const res = await apiFetch('/api/apply-coupon', {
                coupon_code: code,
                cart: getCart(),
            });
            couponKey = res.coupon_key;
            document.getElementById('couponKeyInput').value = couponKey;
            logEvent('accepted', `202 accepted — polling for result`);
            this._poll(code, 0);
        } catch(e) {
            this._setLoading(false);
            const msg = e.data?.message || 'Request failed.';
            this._setStatus('error', msg);
            logEvent('failed', msg);
            window.toast(msg, 'error');
        }
    },

    _poll(code, attempts) {
        if (attempts > 20) {
            this._setLoading(false);
            this._setStatus('error', 'Verification timed out. Please try again.');
            return;
        }
        setTimeout(async () => {
            try {
                const res = await apiFetch(`/api/coupon-status?coupon_key=${couponKey}`, null, 'GET');
                if (res.status === 'pending') { this._poll(code, attempts + 1); return; }
                this._setLoading(false);
                if (res.status === 'valid' && res.reserved) {
                    appliedCoupon = res.coupon;
                    document.getElementById('couponIdInput').value = res.coupon.id;
                    applyDiscount(res.coupon);
                    this._setStatus('success', `${code} applied — ${res.coupon.type === 'percentage' ? res.coupon.value + '% off' : 'RM ' + res.coupon.value + ' off'}`, true);
                    logEvent('reserved', `Coupon reserved · rule v${res.setting_version ?? 1}`);
                    window.toast('Coupon applied!', 'success');
                } else {
                    this._setStatus('error', res.reason || 'Coupon invalid.');
                    logEvent('failed', res.reason || 'Validation failed');
                    window.toast(res.reason || 'Coupon invalid.', 'error');
                }
            } catch(e) { this._poll(code, attempts + 1); }
        }, 600);
    },

    remove() {
        appliedCoupon = null; couponKey = null;
        document.getElementById('couponInput').value = '';
        document.getElementById('couponIdInput').value = '';
        document.getElementById('couponKeyInput').value = '';
        const el = document.getElementById('couponStatus');
        el.classList.add('hidden'); el.classList.remove('flex');
        clearDiscount();
        logEvent('released', 'Coupon reservation released by user');
        window.toast('Coupon removed.', '');
    },

    _setLoading(on) {
        const btn = document.getElementById('applyBtn');
        btn.disabled = on;
        document.getElementById('applySpinner').classList.toggle('hidden', !on);
        document.getElementById('applyBtnText').classList.toggle('hidden', on);
    },

    _setStatus(type, message, showRemove = false) {
        const el  = document.getElementById('couponStatus');
        const dot = document.getElementById('statusDot');
        const txt = document.getElementById('statusText');
        el.classList.remove('hidden'); el.classList.add('flex');
        const dotClass = { processing:'bg-amber-500 animate-pulse', success:'bg-forest', error:'bg-accent' };
        const txtClass = { processing:'text-amber-700', success:'text-forest', error:'text-accent' };
        dot.className = `w-2 h-2 rounded-full flex-shrink-0 ${dotClass[type]}`;
        txt.className = `flex-1 text-sm ${txtClass[type]}`;
        txt.textContent = message;
        document.getElementById('removeBtn').classList.toggle('hidden', !showRemove);
    },
};

// ── Discount helpers ──────────────────────────────────────────────────────
function applyDiscount(coupon) {
    const disc  = coupon.type === 'percentage'? Math.round(SubTotal * coupon.value) / 100 : Math.min(coupon.value, SubTotal);
    const total = SubTotal - disc;
    const row   = document.getElementById('discountRow');
    row.classList.remove('hidden'); row.classList.add('flex');
    document.getElementById('discountBadge').textContent = coupon.code;
    document.getElementById('discountAmount').textContent   = `RM ${disc.toFixed(2)}`;
    document.getElementById('totalAmount').textContent      = `RM ${total.toFixed(2)}`;
    document.getElementById('placeTotal').textContent    = total.toFixed(2);
}
function clearDiscount() {
    const row = document.getElementById('discountRow');
    row.classList.add('hidden'); row.classList.remove('flex');
    document.getElementById('totalAmount').textContent   = `RM ${SubTotal.toFixed(2)}`;
    document.getElementById('placeTotal').textContent = SubTotal.toFixed(2);
}

// ── Event log ─────────────────────────────────────────────────────────────
function logEvent(type, message) {
    const log = document.getElementById('eventLog');
    if (log.querySelector('[data-empty]')) log.innerHTML = '';
    const colors = { dispatched:'#7c6fcd', validated:'#2d6a4f', reserved:'#2d6a4f', released:'#92400e', failed:'#c8522a', accepted:'#185FA5' };
    const now = new Date();
    const ts  = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`;
    const row = document.createElement('div');
    row.className = 'flex items-start gap-2 font-mono text-[11px] leading-relaxed';
    row.innerHTML = `<span class="text-ink-3 flex-shrink-0">${ts}</span><span style="color:${colors[type]||'#4a4540'}" class="font-medium flex-shrink-0">${type}</span><span class="text-ink-2">${message}</span>`;
    log.appendChild(row);
}

// ── Helpers ───────────────────────────────────────────────────────────────
function getCart() {
    return {
        total: SubTotal,
        items: @json($cart),
    };
}

async function apiFetch(url, body, method = 'POST') {
    const opts = {
        method,
        headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN': token },
        credentials: 'same-origin',
    };
    if (body) opts.body = JSON.stringify(body);
    const res = await fetch(url, opts);
    const data = await res.json();
    if (!res.ok) throw { status: res.status, data };
    return data;
}

// ── Global toast ──────────────────────────────────────────────────────────
window.toast = function(msg, type) {
    const t   = document.getElementById('toast');
    const bgMap = { success:'bg-forest', error:'bg-accent', '':'bg-ink' };
    t.className = `fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-xl text-sm font-medium flex items-center gap-2 whitespace-nowrap transition-all duration-300 text-paper ${bgMap[type]||'bg-ink'} translate-y-0 opacity-100`;
    document.getElementById('toast-msg').textContent = msg;
    setTimeout(()=>{ t.className = t.className.replace('translate-y-0 opacity-100','translate-y-24 opacity-0'); }, 3000);
};

// Enter key on coupon input
document.getElementById('couponInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); CouponUI.apply(); }
});

// Intercept form submit to release coupon on failure
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    document.getElementById('placeBtn').disabled = true;
    document.getElementById('placeBtn').textContent = 'Processing…';
});
</script>
@endpush

@endsection