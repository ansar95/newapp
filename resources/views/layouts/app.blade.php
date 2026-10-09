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
<body class="font-sans antialiased bg-maroon-50 text-maroon-950">
<div class="min-h-screen" x-data="{ sidebarOpen: false }">
    <div
        x-show="sidebarOpen"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-40 bg-maroon-950/40 lg:hidden"
        @click="sidebarOpen = false"
        aria-hidden="true"
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-maroon-900 bg-maroon-800 text-white transition-transform duration-200 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        aria-label="Main navigation"
    >
        <div class="flex h-16 items-center border-b border-maroon-700 px-5">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight text-white focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-maroon-800">
                PM Cost Control
            </a>
        </div>
        <nav class="flex-1 space-y-1 overflow-y-auto p-4">
            @php
                $navClass = fn (bool $active) => $active
                    ? 'bg-white text-maroon-900 shadow-sm'
                    : 'text-white hover:bg-maroon-700';
            @endphp
            <a href="{{ route('dashboard') }}"
               class="flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white {{ $navClass(request()->routeIs('dashboard')) }}">
                Dashboard
            </a>
            <a href="{{ route('projects.index') }}"
               class="flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white {{ $navClass(request()->routeIs('projects.*') || (request()->routeIs('boqs.*') && ! request()->routeIs('boqs.approvals', 'boqs.review')) || request()->routeIs('payment*') || request()->routeIs('progress.*') || request()->routeIs('bmhp-purchases.*')) }}">
                Projects
            </a>
            @if (Auth::user()->hasPermission(\App\Enums\Permission::BoqReview))
                @php
                    $pendingBoqApprovalCount = \App\Models\Boq::query()->where('status', \App\Enums\BoqStatus::WaitingApproval)->whereHas('project')->count();
                @endphp
                <a href="{{ route('boqs.approvals') }}"
                   class="flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white {{ $navClass(request()->routeIs('boqs.approvals') || request()->routeIs('boqs.review')) }}">
                    <span>Approval BOQ</span>
                    @if ($pendingBoqApprovalCount > 0)
                        <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-white px-1.5 py-0.5 text-xs font-bold text-maroon-900 ring-1 ring-maroon-300"
                              aria-label="{{ $pendingBoqApprovalCount }} BOQ menunggu approval">
                            {{ $pendingBoqApprovalCount }}
                        </span>
                    @endif
                </a>
            @endif
            @can('viewAny', \App\Models\Asset::class)
                <a href="{{ route('assets.index') }}"
                   class="flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white {{ $navClass(request()->routeIs('assets.*')) }}">
                    Assets
                </a>
            @endcan
            @can('viewAny', \App\Models\MasterBmhp::class)
                <a href="{{ route('master-bmhps.index') }}"
                   class="flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white {{ $navClass(request()->routeIs('master-bmhps.*')) }}">
                    Master BMHP
                </a>
            @endcan
            @if (Auth::user()->hasPermission(\App\Enums\Permission::ReportView))
                <a href="{{ route('reports.index') }}"
                   class="flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white {{ $navClass(request()->routeIs('reports.*')) }}">
                    Reports
                </a>
            @endif
            @if (Auth::user()->isSuperadmin())
                <a href="{{ route('users.index') }}"
                   class="flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-white {{ $navClass(request()->routeIs('users.*')) }}">
                    Users
                </a>
            @endif
        </nav>
        <div class="border-t border-maroon-700 p-4 text-xs font-medium text-white/90">
            {{ Auth::user()->role?->label() }}
        </div>
    </aside>

    <div class="flex min-h-screen min-w-0 flex-col lg:pl-64">
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-maroon-200 bg-white px-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-maroon-300 bg-white px-3 py-2 text-sm font-semibold text-maroon-800 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600 lg:hidden"
                    @click="sidebarOpen = true"
                    aria-label="Open menu"
                >
                    Menu
                </button>
                <div class="min-w-0">
                    <h1 class="truncate text-base font-bold text-maroon-950">{{ $header ?? 'Dashboard' }}</h1>
                    @isset($subheader)
                        <p class="truncate text-xs font-medium text-maroon-700">{{ $subheader }}</p>
                    @endisset
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-3 sm:gap-4">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-semibold text-maroon-950">{{ Auth::user()->name }}</p>
                    <p class="text-xs font-medium text-maroon-700">{{ Auth::user()->role?->label() }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-maroon-300 bg-white px-3 py-1.5 text-sm font-semibold text-maroon-800 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600">
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            @include('partials.flash')
            {{ $slot }}
        </main>
    </div>
</div>
<style>[x-cloak]{display:none!important}</style>
</body>
</html>
