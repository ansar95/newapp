<?php

namespace App\Http\Controllers;

use App\Actions\ManPower\AssignManPowerToProject;
use App\Actions\ManPower\CancelManPowerTransaction;
use App\Actions\ManPower\RecordManPowerTransaction;
use App\Actions\ManPower\UpdateProjectManPower;
use App\Enums\ManPowerTransactionType;
use App\Models\ManPower;
use App\Models\ManPowerTransaction;
use App\Models\Project;
use App\Models\ProjectManPower;
use App\Support\ProjectDetailContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectManPowerController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('viewForProject', [ProjectManPower::class, $project]);

        $context = ProjectDetailContext::for($project, $request->user());

        $members = $project->manPowers()
            ->with(['manPower', 'transactions' => fn ($query) => $query->with(['creator', 'canceller'])->orderByDesc('transaction_date')->orderByDesc('id')])
            ->orderBy('id')
            ->get();

        $availableManPowers = ManPower::query()
            ->where('is_active', true)
            ->whereNotIn('id', $members->pluck('man_power_id'))
            ->orderBy('name')
            ->get();

        return view('projects.man-power.index', [
            'context' => $context,
            'project' => $project,
            'tab' => 'man-power',
            'members' => $members,
            'availableManPowers' => $availableManPowers,
            'transactionTypes' => ManPowerTransactionType::cases(),
            'totalSalary' => round((float) $members->sum(fn (ProjectManPower $member) => $member->totalSalary()), 2),
            'totalKasbon' => round((float) $members->sum(fn (ProjectManPower $member) => $member->paidAmount(ManPowerTransactionType::Kasbon)), 2),
            'totalPelunasan' => round((float) $members->sum(fn (ProjectManPower $member) => $member->paidAmount(ManPowerTransactionType::Pelunasan)), 2),
            'totalRemaining' => round((float) $members->sum(fn (ProjectManPower $member) => $member->remainingSalary()), 2),
        ]);
    }

    public function store(Request $request, Project $project, AssignManPowerToProject $assignManPowerToProject): RedirectResponse
    {
        $this->authorize('assign', [ProjectManPower::class, $project]);

        $assignManPowerToProject->handle($request->user(), $project, $request->all());

        return back()->with('success', 'Man power berhasil ditambahkan ke project.');
    }

    public function update(Request $request, ProjectManPower $projectManPower, UpdateProjectManPower $updateProjectManPower): RedirectResponse
    {
        $this->authorize('update', $projectManPower);

        $updateProjectManPower->handle($projectManPower, $request->all());

        return back()->with('success', 'Data man power berhasil diperbarui.');
    }

    public function storeTransaction(Request $request, ProjectManPower $projectManPower, RecordManPowerTransaction $recordManPowerTransaction): RedirectResponse
    {
        $this->authorize('recordTransaction', $projectManPower);

        $transaction = $recordManPowerTransaction->handle($request->user(), $projectManPower, $request->all());

        return back()->with('success', $transaction->type->label().' berhasil dicatat.');
    }

    public function cancelTransaction(Request $request, ManPowerTransaction $manPowerTransaction, CancelManPowerTransaction $cancelManPowerTransaction): RedirectResponse
    {
        $this->authorize('cancel', $manPowerTransaction);

        $cancelManPowerTransaction->handle($request->user(), $manPowerTransaction, $request->all());

        return back()->with('success', 'Transaksi man power berhasil dibatalkan.');
    }
}
