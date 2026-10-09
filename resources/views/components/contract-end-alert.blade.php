@props(['project'])

@php
    $daysRemaining = $project->contractDaysRemaining();
@endphp

@if ($project->isContractOverdue())
    <div role="alert" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border border-maroon-800 bg-maroon-800 px-4 py-3 text-sm text-white']) }}>
        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-xs font-bold text-maroon-800">!</span>
        <div>
            <p class="font-bold">Kontrak sudah berakhir {{ abs($daysRemaining) }} hari yang lalu.</p>
            <p class="mt-0.5 text-white/90">
                Tanggal akhir kontrak <x-date-display :value="$project->target_end_date" />, project belum diselesaikan. Segera tindak lanjuti (perpanjangan/addendum atau penyelesaian project).
            </p>
        </div>
    </div>
@elseif ($project->isContractEndingSoon())
    <div role="alert" {{ $attributes->merge(['class' => 'flex items-start gap-3 rounded-lg border border-maroon-400 bg-maroon-100 px-4 py-3 text-sm text-maroon-950']) }}>
        <span class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-maroon-800 text-xs font-bold text-white">!</span>
        <div>
            <p class="font-bold">
                @if ($daysRemaining === 0)
                    Kontrak berakhir hari ini.
                @else
                    Kontrak akan berakhir dalam {{ $daysRemaining }} hari.
                @endif
            </p>
            <p class="mt-0.5 text-maroon-800">
                Tanggal akhir kontrak <x-date-display :value="$project->target_end_date" />. Pastikan pekerjaan dan pembayaran selesai tepat waktu.
            </p>
        </div>
    </div>
@endif
