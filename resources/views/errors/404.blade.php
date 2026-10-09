<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 — {{ config('app.name', 'PM Cost Control') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-maroon-50 font-sans antialiased text-maroon-900">
    <div class="mx-4 w-full max-w-lg rounded-xl border border-maroon-200 bg-white p-8 text-center shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-wide text-maroon-700">404</p>
        <h1 class="mt-2 text-xl font-semibold text-maroon-950">Halaman tidak ditemukan</h1>
        <p class="mt-3 text-sm text-maroon-700">Halaman yang Anda cari tidak tersedia atau telah dipindahkan.</p>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}"
           class="mt-6 inline-flex rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">
            Kembali
        </a>
    </div>
</body>
</html>
