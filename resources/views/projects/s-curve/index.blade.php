<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Kurva S</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Kurva S', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    @include('s-curve._panel', [
        'project' => $project,
        'series' => $series,
        'canManage' => $canManage,
        'returnTo' => $returnTo,
        'planItems' => $planItems,
    ])
</x-app-layout>
