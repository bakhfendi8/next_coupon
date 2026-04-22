{{-- Global toast — controlled by window.toast(msg, type) in app.js --}}
<div id="toast" class="fixed bottom-6 left-1/2 -translate-x-1/2 translate-y-24 opacity-0 
pointer-events-none transition-all duration-300 z-50 bg-ink text-paper px-5 py-3 rounded-xl 
text-sm font-medium flex items-center gap-2 whitespace-nowrap max-w-sm">
    <span id="toast-msg"></span>
</div>