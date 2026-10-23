@props(['monitor', 'project'])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-maroon-200 bg-white p-4 shadow-sm']) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-bold uppercase tracking-wide text-maroon-700">Arsip Dokumen</p>
        <a href="{{ route('projects.documents.index', $project) }}" class="text-xs font-medium text-maroon-900 underline">Lihat Dokumen</a>
    </div>
    <p class="mt-3 text-sm text-maroon-800"><span class="text-lg font-bold tabular-nums text-maroon-950">{{ $monitor->total() }}</span> berkas tersimpan</p>
</div>
