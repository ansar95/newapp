<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-md border border-transparent bg-maroon-800 px-4 py-2 text-xs font-bold uppercase tracking-widest text-white transition hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
