<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 — {{ config('app.name', 'PM Cost Control') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-maroon-50 font-sans antialiased text-maroon-900">
    <div class="mx-4 w-full max-w-lg rounded-xl border border-maroon-200 bg-white p-8 text-center shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-wide text-maroon-900">403</p>
        <h1 class="mt-2 text-xl font-semibold text-maroon-950">Akses ditolak</h1>
        <p class="mt-3 text-sm text-maroon-700">Anda tidak memiliki akses ke halaman ini.</p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
           class="mt-6 inline-flex rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">
            Kembali
        </a>
    </div>
</body>
</html>
