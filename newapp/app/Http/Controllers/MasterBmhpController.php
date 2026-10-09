<?php

namespace App\Http\Controllers;

use App\Actions\Bmhp\CreateMasterBmhp;
use App\Actions\Bmhp\UpdateMasterBmhp;
use App\Enums\MasterBmhpStatus;
use App\Models\MasterBmhp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterBmhpController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', MasterBmhp::class);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $category = trim((string) $request->query('category', ''));

        $masterBmhps = MasterBmhp::query()
            ->withCount('purchaseDetails')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('master-bmhps.index', [
            'masterBmhps' => $masterBmhps,
            'search' => $search,
            'status' => $status,
            'category' => $category,
            'statuses' => MasterBmhpStatus::cases(),
            'categories' => MasterBmhp::categoryOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MasterBmhp::class);

        return view('master-bmhps.create', [
            'masterBmhp' => null,
            'suggestedCode' => MasterBmhp::nextCode(),
            'categories' => MasterBmhp::categoryOptions(),
            'statuses' => MasterBmhpStatus::cases(),
        ]);
    }

    public function store(Request $request, CreateMasterBmhp $createMasterBmhp): RedirectResponse
    {
        $this->authorize('create', MasterBmhp::class);

        $createMasterBmhp->handle($request->user(), $request->all());

        return redirect()
            ->route('master-bmhps.index')
            ->with('success', 'Master BMHP berhasil dibuat.');
    }

    public function edit(MasterBmhp $masterBmhp): View
    {
        $this->authorize('update', $masterBmhp);

        return view('master-bmhps.edit', [
            'masterBmhp' => $masterBmhp,
            'suggestedCode' => $masterBmhp->code,
            'categories' => MasterBmhp::categoryOptions(),
            'statuses' => MasterBmhpStatus::cases(),
        ]);
    }

    public function update(Request $request, MasterBmhp $masterBmhp, UpdateMasterBmhp $updateMasterBmhp): RedirectResponse
    {
        $this->authorize('update', $masterBmhp);

        $updateMasterBmhp->handle($request->user(), $masterBmhp, $request->all());

        return redirect()
            ->route('master-bmhps.index')
            ->with('success', 'Master BMHP berhasil diperbarui.');
    }

    public function destroy(MasterBmhp $masterBmhp): RedirectResponse
    {
        $this->authorize('delete', $masterBmhp);

        if ($masterBmhp->isUsedInPurchases()) {
            $masterBmhp->status = MasterBmhpStatus::Inactive;
            $masterBmhp->save();

            return back()->with('success', 'BMHP sudah dipakai pada pembelian. Status diubah menjadi INACTIVE.');
        }

        $masterBmhp->delete();

        return redirect()
            ->route('master-bmhps.index')
            ->with('success', 'Master BMHP berhasil dihapus.');
    }
}
