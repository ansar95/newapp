<?php

namespace App\Http\Controllers;

use App\Actions\SCurve\EstablishSCurveFromItems;
use App\Actions\SCurve\ImportSCurveBaseline;
use App\Actions\SCurve\PrepareSCurveBaseline;
use App\Actions\SCurve\SaveSCurveBaseline;
use App\Enums\SCurveGranularity;
use App\Enums\SCurveKind;
use App\Models\Project;
use App\Models\SCurveBaseline;
use App\Support\ProjectDetailContext;
use App\Support\SCurveSeries;
use App\Support\SpreadsheetFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SCurveController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::query()->orderBy('code')->get(['id', 'code', 'name']);
        $project = null;
        $panel = null;

        if ($request->filled('project')) {
            $project = Project::query()->find($request->integer('project'));

            if ($project !== null) {
                $this->authorize('view', $project);
                $panel = $this->present($request, $project, 'menu');
            }
        }

        return view('s-curve.index', [
            'projects' => $projects,
            'selectedProject' => $project,
            'panel' => $panel,
        ]);
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        return view('projects.s-curve.index', [
            ...$this->present($request, $project, 'project'),
            'context' => ProjectDetailContext::for($project, $request->user()),
            'tab' => 's-curve',
        ]);
    }

    public function store(Request $request, Project $project, PrepareSCurveBaseline $prepare): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', SCurveBaseline::class);

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(SCurveKind::class)],
            'granularity' => ['nullable', Rule::enum(SCurveGranularity::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'return_to' => ['nullable', 'in:menu,project'],
        ], [
            'kind.required' => 'Jenis rencana wajib dipilih.',
            'reason.max' => 'Alasan maksimal 1000 karakter.',
        ]);

        $kind = SCurveKind::from($validated['kind']);
        $granularity = isset($validated['granularity'])
            ? SCurveGranularity::from($validated['granularity'])
            : null;

        if ($kind === SCurveKind::Initial && $granularity === null) {
            throw ValidationException::withMessages([
                'granularity' => 'Pilih periode mingguan atau bulanan.',
            ]);
        }

        $prepare->handle($request->user(), $project, $kind, $granularity, $validated['reason'] ?? null);

        return $this->redirectTo(
            $request,
            $project,
            'Keranjang periode rencana sudah disiapkan. Isi persen tiap periode, lalu tetapkan.',
        );
    }

    public function storePlan(Request $request, Project $project, EstablishSCurveFromItems $establish): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', SCurveBaseline::class);

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(SCurveKind::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
            'return_to' => ['nullable', 'in:menu,project'],
            'items' => ['required', 'array'],
            'items.*.start' => ['nullable', 'date'],
            'items.*.finish' => ['nullable', 'date'],
        ], [
            'items.required' => 'Isi tanggal setiap item BOQ.',
            'items.*.start.date' => 'Tanggal mulai item tidak valid.',
            'items.*.finish.date' => 'Tanggal selesai item tidak valid.',
            'reason.max' => 'Alasan maksimal 1000 karakter.',
        ]);

        $establish->handle(
            $request->user(),
            $project,
            SCurveKind::from($validated['kind']),
            $validated['items'],
            $validated['reason'] ?? null,
        );

        return $this->redirectTo($request, $project, 'Rencana Kurva S ditetapkan dari tanggal item BOQ.');
    }

    public function update(Request $request, SCurveBaseline $baseline, SaveSCurveBaseline $save): RedirectResponse
    {
        $this->authorize('update', $baseline);
        $baseline->loadMissing('project');

        $validated = $request->validate([
            'periods' => ['required', 'array'],
            'periods.*' => ['required', 'numeric', 'min:0', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'establish' => ['nullable', 'boolean'],
            'return_to' => ['nullable', 'in:menu,project'],
        ], [
            'periods.required' => 'Isi persen rencana untuk setiap periode.',
            'periods.*.required' => 'Setiap periode wajib diisi.',
            'periods.*.numeric' => 'Persen rencana harus berupa angka.',
            'periods.*.min' => 'Persen rencana berada di antara 0 dan 100.',
            'periods.*.max' => 'Persen rencana berada di antara 0 dan 100.',
            'reason.max' => 'Alasan maksimal 1000 karakter.',
        ]);

        $save->handle(
            $request->user(),
            $baseline,
            $validated['periods'],
            $validated['reason'] ?? null,
            $request->boolean('establish'),
        );

        return $this->redirectTo(
            $request,
            $baseline->project,
            $request->boolean('establish') ? 'Rencana Kurva S ditetapkan.' : 'Draf rencana disimpan.',
        );
    }

    public function import(Request $request, SCurveBaseline $baseline, ImportSCurveBaseline $import): RedirectResponse
    {
        $this->authorize('update', $baseline);
        $baseline->loadMissing('project');

        $request->validate([
            'return_to' => ['nullable', 'in:menu,project'],
        ]);

        $import->handle($request->user(), $baseline, $request->file('file'));

        return $this->redirectTo($request, $baseline->project, 'Rencana Kurva S diimpor dan ditetapkan.');
    }

    public function template(SCurveBaseline $baseline): StreamedResponse
    {
        $this->authorize('update', $baseline);
        $baseline->loadMissing(['project', 'periods']);

        $rows = [['period_start', 'period_end', 'planned_percent']];

        foreach ($baseline->periods as $period) {
            $rows[] = [
                $period->period_start->toDateString(),
                $period->period_end->toDateString(),
                (float) $period->planned_percent,
            ];
        }

        $code = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $baseline->project->code) ?: 'project';

        return SpreadsheetFile::download($rows, 'kurva-s-'.$code.'.xlsx', 'Kurva S');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Request $request, Project $project, string $returnTo): array
    {
        return [
            'project' => $project,
            'series' => SCurveSeries::for($project, $this->display($request, $project)),
            'canManage' => (bool) $request->user()?->can('create', SCurveBaseline::class),
            'returnTo' => $returnTo,
            'planItems' => $this->planItems($project),
        ];
    }

    private function display(Request $request, Project $project): SCurveGranularity
    {
        $requested = SCurveGranularity::tryFrom((string) $request->query('periode', ''));

        if ($requested !== null) {
            return $requested;
        }

        $project->loadMissing('sCurveBaselines');

        $initial = $project->sCurveBaselines->first(
            fn (SCurveBaseline $baseline) => $baseline->kind === SCurveKind::Initial && $baseline->isEstablished()
        );

        return $initial?->granularity ?? SCurveGranularity::Week;
    }

    /**
     * Approved BOQ items with their value weight and the dates saved on the initial plan.
     *
     * @return list<array{cogs: string, items: list<array{id: int, code: string, name: string, weight: float, progress: float, start: string|null, finish: string|null}>}>
     */
    private function planItems(Project $project): array
    {
        $project->loadMissing(['approvedBoqs.cogs.items', 'sCurveBaselines.itemSchedules']);
        $total = $project->totalCost();
        $saved = $project->sCurveBaselines
            ->first(fn (SCurveBaseline $baseline) => $baseline->kind === SCurveKind::Initial && $baseline->isEstablished())
            ?->itemSchedules
            ->keyBy('boq_item_id');
        $groups = [];

        foreach ($project->approvedBoqs as $boq) {
            foreach ($boq->cogs as $cogs) {
                $items = [];

                foreach ($cogs->items as $item) {
                    $schedule = $saved?->get($item->id);
                    $items[] = [
                        'id' => $item->id,
                        'code' => $item->code,
                        'name' => $item->name,
                        'weight' => $total > 0 ? round(((float) $item->total_price / $total) * 100, 2) : 0.0,
                        'progress' => (float) $item->progress_percentage,
                        'start' => $schedule?->planned_start?->toDateString(),
                        'finish' => $schedule?->planned_finish?->toDateString(),
                    ];
                }

                if ($items !== []) {
                    $groups[] = [
                        'cogs' => $cogs->name,
                        'items' => $items,
                    ];
                }
            }
        }

        return $groups;
    }

    private function redirectTo(Request $request, Project $project, string $message): RedirectResponse
    {
        $redirect = $request->input('return_to') === 'menu'
            ? redirect()->route('s-curve.index', ['project' => $project->id])
            : redirect()->route('projects.s-curve.show', $project);

        return $redirect->with('success', $message);
    }
}
