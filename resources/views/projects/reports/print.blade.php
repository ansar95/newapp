<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan {{ $printAll ? 'Lengkap' : $sections[0]->label() }} — {{ $project->code }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            a { text-decoration: none; color: inherit; }
        }
    </style>
</head>
<body class="bg-white font-sans text-maroon-950 antialiased">
<div class="mx-auto max-w-7xl p-6 print:max-w-none print:p-0">
    <div class="mb-4 flex justify-end gap-2 print:hidden">
        <button type="button" onclick="window.print()"
                class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">Cetak / Simpan PDF</button>
        <button type="button" onclick="window.close()"
                class="rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">Tutup</button>
    </div>

    <header class="mb-6 border-b-2 border-maroon-800 pb-4">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-maroon-700">{{ config('app.name', 'PM Cost Control') }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight">
            Laporan {{ $printAll ? 'Lengkap Project' : $sections[0]->label() }}
        </h1>
        <p class="mt-1 text-base font-medium text-maroon-800">{{ $project->code }} — {{ $project->name }}</p>
        <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            <div><dt class="inline text-maroon-700">Client:</dt> <dd class="inline font-medium">{{ $project->client_name }}</dd></div>
            <div><dt class="inline text-maroon-700">Nomor Kontrak:</dt> <dd class="inline font-medium">{{ $project->contract_number ?? '-' }}</dd></div>
            <div><dt class="inline text-maroon-700">Periode Transaksi:</dt> <dd class="inline font-medium">{{ $report->periodLabel() }}</dd></div>
            <div><dt class="inline text-maroon-700">Dicetak:</dt> <dd class="inline font-medium">{{ date_id(now(), 'd/m/Y H:i') }} oleh {{ $report->viewer->name }}</dd></div>
        </dl>
    </header>

    @foreach ($sections as $section)
        <section @class(['mb-8', 'break-before-page pt-2' => ! $loop->first])>
            @if ($printAll)
                <h2 class="mb-1 text-xl font-bold tracking-tight">{{ $loop->iteration }}. {{ $section->label() }}</h2>
            @endif
            <p class="mb-4 text-sm text-maroon-700">
                {{ $section->description() }}
                {{ $section->usesPeriod() ? 'Periode: '.$report->periodLabel().'.' : 'Posisi saat ini.' }}
            </p>
            @include('projects.reports.sections.'.$section->value, ['report' => $report])
        </section>
    @endforeach
</div>

<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 300));
</script>
</body>
</html>
