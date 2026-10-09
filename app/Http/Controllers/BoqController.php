<?php

namespace App\Http\Controllers;

use App\Actions\BOQ\CreateBoqItem;
use App\Actions\BOQ\CreateCogs;
use App\Actions\BOQ\ExportBoqCogs;
use App\Actions\BOQ\ImportBoqCogs;
use App\Actions\BOQ\SubmitBOQ;
use App\Actions\BOQ\UpdateBoqItem;
use App\Enums\Permission;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Support\ProjectActualCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoqController extends Controller
{
    public function show(Boq $boq): View
    {
        $this->authorize('view', $boq);

        $boq->load(['project.approvedBoqs.cogs.items', 'cogs.items', 'submitter', 'reviewer', 'revisions.revisionItems']);

        return view('boqs.show', [
            'boq' => $boq,
            'project' => $boq->project,
            'tab' => 'boq',
            'overallProgress' => $boq->project->progressPercentage(),
            'actualCost' => request()->user()->hasPermission(Permission::BmhpView)
                ? ProjectActualCost::forProject($boq->project)
                : null,
        ]);
    }

    public function submit(Boq $boq, SubmitBOQ $submitBOQ): RedirectResponse
    {
        $this->authorize('submit', $boq);

        $submitBOQ->handle(request()->user(), $boq->fresh(['project', 'cogs.items']));

        return redirect()
            ->route('boqs.show', $boq)
            ->with('success', 'BOQ berhasil disubmit.');
    }

    public function storeCogs(Request $request, Boq $boq, CreateCogs $createCogs): RedirectResponse
    {
        $this->authorize('update', $boq);

        $createCogs->handle($request->user(), $boq, $request->all());

        return back()->with('success', 'COGS berhasil ditambahkan.');
    }

    public function storeItem(Request $request, BoqCogs $boqCogs, CreateBoqItem $createBoqItem): RedirectResponse
    {
        $boqCogs->loadMissing('boq');
        $this->authorize('update', $boqCogs->boq);

        $createBoqItem->handle($request->user(), $boqCogs, $request->all());

        return back()->with('success', 'Item berhasil ditambahkan.');
    }

    public function updateItem(Request $request, BoqItem $item, UpdateBoqItem $updateBoqItem): RedirectResponse
    {
        $item->loadMissing('cogs.boq');
        $this->authorize('update', $item->cogs->boq);

        $updateBoqItem->handle($request->user(), $item, $request->all());

        return back()->with('success', 'Item berhasil diperbarui.');
    }

    public function exportCogs(Boq $boq, ExportBoqCogs $exportBoqCogs): StreamedResponse
    {
        $this->authorize('view', $boq);

        return $exportBoqCogs->handle(request()->user(), $boq);
    }

    public function importCogs(Request $request, Boq $boq, ImportBoqCogs $importBoqCogs): RedirectResponse
    {
        $this->authorize('update', $boq);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Pilih file CSV untuk diimpor.',
            'file.mimes' => 'File harus berformat CSV.',
            'file.max' => 'Ukuran file maksimal 2MB.',
        ]);

        $result = $importBoqCogs->handle($request->user(), $boq, $request->file('file'));

        return back()->with(
            'success',
            sprintf(
                'Import selesai: %d COGS baru, %d item baru, %d item dilewati (kode sudah ada).',
                $result['cogs_created'],
                $result['items_created'],
                $result['items_skipped']
            )
        );
    }

    public function cogsTemplate(ExportBoqCogs $exportBoqCogs): StreamedResponse
    {
        $this->authorize('create', Boq::class);

        return $exportBoqCogs->template();
    }
}
