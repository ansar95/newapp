<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-md border border-transparent bg-maroon-900 px-4 py-2 text-xs font-bold uppercase tracking-widest text-white transition hover:bg-maroon-950 focus:outline-none focus:ring-2 focus:ring-maroon-700 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
