<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-widest text-maroon-800 shadow-sm transition hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2 disabled:opacity-25']) }}>
    {{ $slot }}
</button>
