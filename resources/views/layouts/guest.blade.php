<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@isset($header){{ $header }} — @endisset{{ config('app.name', 'PM Cost Control') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-maroon-950">
<div class="min-h-screen lg:grid lg:grid-cols-2">
    <aside class="relative hidden bg-maroon-800 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-white/90">Project · Cost · Control</p>
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-white xl:text-5xl">PM Cost Control</h1>
            <p class="mt-4 max-w-md text-base font-medium leading-relaxed text-white">
                Kelola project dari BOQ, approval, pembayaran, progress, hingga asset — dalam satu alur yang jelas.
            </p>
        </div>
        <div class="space-y-2 text-sm font-medium text-white/90">
            <p>BOQ → Approval → Payment → Progress</p>
            <p>Asset terpisah dari perhitungan biaya project.</p>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col justify-center bg-white px-4 py-10 sm:px-8">
        <div class="mx-auto w-full max-w-md">
            <div class="mb-8 lg:hidden">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-maroon-700">PM Cost Control</p>
                <h1 class="mt-2 text-2xl font-bold text-maroon-950">Masuk ke akun Anda</h1>
            </div>
            <div class="rounded-2xl border border-maroon-200 bg-white p-6 shadow-lg shadow-maroon-100 sm:p-8">
                <div class="mb-6 hidden lg:block">
                    <h2 class="text-xl font-bold tracking-tight text-maroon-950">{{ $header ?? 'Masuk' }}</h2>
                    @isset($subheader)
                        <p class="mt-1 text-sm font-medium text-maroon-700">{{ $subheader }}</p>
                    @else
                        <p class="mt-1 text-sm font-medium text-maroon-700">Gunakan kredensial yang diberikan administrator.</p>
                    @endisset
                </div>
                {{ $slot }}
            </div>
            <p class="mt-6 text-center text-xs font-medium text-maroon-700">© {{ date('Y') }} PM Cost Control</p>
        </div>
    </div>
</div>
</body>
</html>
