{{-- Reusable coupon input box --}}
{{-- Usage: @include('partials.couponbox') --}}

<div class="border border-ink/18 rounded-xl bg-paper overflow-hidden" id="couponbox">

    <div class="flex items-stretch">
        <input
            type="text"
            id="couponInput"
            placeholder="Enter coupon code"
            maxlength="20"
            autocomplete="off"
            class="flex-1 px-4 py-3.5 font-mono text-sm tracking-widest uppercase
            bg-transparent border-none focus:outline-none focus:ring-0
            text-ink placeholder:normal-case placeholder:tracking-normal
            placeholder:font-sans placeholder:text-ink-3">

        <button
            id="applyBtn"
            type="button"
            onclick="CouponUI.apply()"
            class="px-6 bg-ink text-paper text-sm font-semibold min-w-[90px]
             flex items-center justify-center gap-2
             transition-all hover:bg-ink/85 active:scale-[.98]
             disabled:opacity-40 disabled:cursor-not-allowed disabled:scale-100">
            <svg id="applySpinner"
                class="hidden w-3.5 h-3.5 animate-spin"
                viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            <span id="applyBtnText">Apply</span>
        </button>
    </div>

    {{-- Status bar --}}
    <div id="couponStatus" class="hidden border-t border-ink/10 px-4 py-2.5 items-center gap-2.5 min-h-[42px]">
        <div id="statusDot" class="w-2 h-2 rounded-full flex-shrink-0"></div>
            <span id="statusText" class="flex-1 text-sm"></span>
            <button
                id="removeBtn"
                type="button"
                onclick="CouponUI.remove()"
                class="hidden font-mono text-xs text-ink-3 underline underline-offset-2
                    hover:text-accent transition-colors bg-transparent border-none cursor-pointer">
                remove
            </button>
        </div>
    </div>

    {{-- Sample codes hint --}}
    <p class="font-mono text-xs text-ink-3 mt-2">
        Try:
        <button type="button" onclick="CouponUI.fill('SAVE10')"
            class="underline underline-offset-2 hover:text-accent transition-colors bg-transparent border-none cursor-pointer font-mono text-xs text-ink-3">SAVE10</button>
        &nbsp;·&nbsp;
        <button type="button" onclick="CouponUI.fill('FIRST50')"
            class="underline underline-offset-2 hover:text-accent transition-colors bg-transparent border-none cursor-pointer font-mono text-xs text-ink-3">FIRST50</button>
        &nbsp;·&nbsp;
        <button type="button" onclick="CouponUI.fill('BADCODE')"
            class="underline underline-offset-2 hover:text-accent transition-colors bg-transparent border-none cursor-pointer font-mono text-xs text-ink-3">BADCODE</button>
    </p>