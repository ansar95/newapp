<?php

namespace App\Http\Controllers;

use App\Actions\BOQ\PrepareBoqForRevision;
use App\Actions\BOQ\SubmitRevision;
use App\Actions\BOQ\UpdateBoqItem;
use App\Enums\BoqStatus;
use App\Enums\RevisionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\BoqRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoqRevisionController extends Controller
{
    public function show(Boq $boq): View
    {
        $this->authorize('viewForBoq', [BoqRevision::class, $boq]);

        if (! in_array($boq->status, [BoqStatus::RevisionRequired, BoqStatus::Draft], true)) {
            throw BusinessRuleException::make(
                'Halaman revisi hanya tersedia untuk BOQ dengan status REVISION_REQUIRED atau DRAFT hasil revisi.'
            );
        }

        $boq->load([
            'project',
            'cogs.items',
            'revisions' => fn ($query) => $query->latest('revision_number'),
            'revisions.revisionItems.item.cogs',
        ]);

        $latestRevision = $boq->revisions->firstWhere('status', RevisionStatus::Open)
            ?? $boq->revisions->first();

        return view('boqs.revision', [
            'boq' => $boq,
            'project' => $boq->project,
            'revision' => $latestRevision,
        ]);
    }

    public function prepare(Boq $boq, PrepareBoqForRevision $prepareBoqForRevision): RedirectResponse
    {
        $this->authorize('prepare', [BoqRevision::class, $boq]);

        $prepareBoqForRevision->handle(request()->user(), $boq);

        return back()->with('success', 'BOQ dibuka untuk perbaikan.');
    }

    public function updateItem(Request $request, BoqItem $item, UpdateBoqItem $updateBoqItem): RedirectResponse
    {
        $item->loadMissing('cogs.boq');
        $this->authorize('update', [BoqRevision::class, $item->cogs->boq]);

        $updateBoqItem->handle($request->user(), $item, $request->only([
            'name',
            'description',
            'notes',
            'level_3',
            'quantity',
            'unit',
            'material_unit_price',
            'service_unit_price',
            'unit_price',
            'sort_order',
        ]));

        return back()->with('success', 'Item revisi berhasil diperbarui.');
    }

    public function submit(Boq $boq, SubmitRevision $submitRevision): RedirectResponse
    {
        $this->authorize('submit', [BoqRevision::class, $boq]);

        $submitRevision->handle(request()->user(), $boq);

        return redirect()
            ->route('boqs.show', $boq)
            ->with('success', 'BOQ hasil revisi berhasil disubmit.');
    }
}
