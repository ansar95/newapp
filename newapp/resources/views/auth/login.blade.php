<x-guest-layout>
    <x-slot name="header">Masuk</x-slot>
    <x-slot name="subheader">Akses dashboard project, BOQ, payment, dan laporan.</x-slot>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="nama@perusahaan.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between gap-3">
                <x-input-label for="password" value="Password" />
                @if (Route::has('password.request'))
                    <a class="text-xs font-semibold text-maroon-700 hover:text-maroon-950" href="{{ route('password.request') }}">
                        Lupa password?
                    </a>
                @endif
            </div>
            <x-text-input id="password" class="mt-1.5 block w-full"
                          type="password"
                          name="password"
                          required autocomplete="current-password"
                          placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember_me" class="inline-flex items-center gap-2">
                <input id="remember_me" type="checkbox"
                       class="rounded border-maroon-300 text-maroon-800 shadow-sm focus:ring-maroon-600"
                       name="remember">
                <span class="text-sm font-medium text-maroon-800">Ingat saya</span>
            </label>
        </div>

        <button type="submit"
                class="inline-flex w-full items-center justify-center rounded-lg bg-maroon-800 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
            Masuk
        </button>
    </form>
</x-guest-layout>
