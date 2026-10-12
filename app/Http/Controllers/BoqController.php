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
use App\Support\BoqCogsCsv;
use App\Support\ProjectActualCost;
use App\Support\SpreadsheetFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
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
            'actualCost' => request()->user()->hasPermission(Permission::ProcurementView)
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

        $request->validate(['file' => SpreadsheetFile::UPLOAD_RULES], SpreadsheetFile::uploadMessages());

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

    /**
     * Reads an Excel/CSV COGS file for the Create BOQ form without saving anything.
     */
    public function parseCogsFile(Request $request): JsonResponse
    {
        $this->authorize('create', Boq::class);

        $request->validate(['file' => SpreadsheetFile::UPLOAD_RULES], SpreadsheetFile::uploadMessages());

        try {
            $rows = BoqCogsCsv::parse($request->file('file'));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return response()->json([
            'rows' => $rows->map(fn (array $row) => Arr::except($row, '_line'))->values(),
        ]);
    }

    public function exportCogsForm(Request $request): StreamedResponse
    {
        $this->authorize('create', Boq::class);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'max:5000'],
            'rows.*' => ['array'],
            'rows.*.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $numericColumns = ['quantity', 'material_price', 'service_price'];
        $rows = [BoqCogsCsv::HEADERS];

        foreach ($validated['rows'] as $row) {
            $rows[] = array_map(function (string $column) use ($row, $numericColumns): string|int|float {
                $value = trim((string) ($row[$column] ?? ''));

                if (in_array($column, $numericColumns, true) && is_numeric($value)) {
                    return $value + 0;
                }

                return $value;
            }, BoqCogsCsv::HEADERS);
        }

        return SpreadsheetFile::download($rows, 'boq-cogs-form.xlsx', 'COGS');
    }
}
