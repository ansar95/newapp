@props(['project'])

@php
    $daysRemaining = $project->contractDaysRemaining();
@endphp

@if ($project->isContractOverdue())
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-maroon-800 px-2 py-0.5 text-xs font-semibold text-white']) }}>
        Lewat {{ abs($daysRemaining) }} hari
    </span>
@elseif ($project->isContractEndingSoon())
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full border border-maroon-400 bg-maroon-100 px-2 py-0.5 text-xs font-semibold text-maroon-900']) }}>
        {{ $daysRemaining === 0 ? 'Berakhir hari ini' : 'Sisa '.$daysRemaining.' hari' }}
    </span>
@endif
