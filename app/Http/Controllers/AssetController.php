<?php

namespace App\Http\Controllers;

use App\Actions\Asset\CreateAsset;
use App\Actions\Asset\ExportAssets;
use App\Actions\Asset\ImportAssets;
use App\Actions\Asset\MarkAssetAvailable;
use App\Actions\Asset\UpdateAsset;
use App\Enums\AssetCondition;
use App\Enums\AssetRequestStatus;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $category = trim((string) $request->query('category', ''));
        $source = trim((string) $request->query('source', ''));

        $assets = Asset::query()
            ->with(['activeAssignment.project'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($source !== '', fn ($query) => $query->where('source_type', $source))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('assets.index', [
            'assets' => $assets,
            'search' => $search,
            'status' => $status,
            'category' => $category,
            'source' => $source,
            'statuses' => AssetStatus::cases(),
            'categories' => Asset::categoryOptions(),
            'sources' => AssetSourceType::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);

        return view('assets.create', [
            'categories' => Asset::categoryOptions(),
            'statuses' => AssetStatus::cases(),
            'sources' => AssetSourceType::cases(),
            'conditions' => AssetCondition::cases(),
            'requestStatuses' => AssetRequestStatus::cases(),
        ]);
    }

    public function store(Request $request, CreateAsset $createAsset): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $asset = $createAsset->handle(
            $request->user(),
            $request->all(),
            $request->file('request_letter_file')
        );

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Master asset berhasil dibuat.');
    }

    public function show(Asset $asset): View
    {
        $this->authorize('view', $asset);

        $asset->load([
            'activeAssignment.project',
            'activeAssignment.assignedBy',
            'projectAssets.project' => fn ($query) => $query->withTrashed(),
            'projectAssets.assignedBy',
            'projectAssets.releasedBy',
            'creator',
            'requester',
        ]);

        return view('assets.show', [
            'asset' => $asset,
            'currentAssignment' => $asset->activeAssignment,
            'history' => $asset->projectAssets->sortByDesc('assigned_at')->values(),
        ]);
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);

        return view('assets.edit', [
            'asset' => $asset,
            'categories' => Asset::categoryOptions(),
            'statuses' => AssetStatus::cases(),
            'sources' => AssetSourceType::cases(),
            'conditions' => AssetCondition::cases(),
            'requestStatuses' => AssetRequestStatus::cases(),
        ]);
    }

    public function update(Request $request, Asset $asset, UpdateAsset $updateAsset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $updateAsset->handle(
            $request->user(),
            $asset,
            $request->all(),
            $request->file('request_letter_file')
        );

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', 'Master asset berhasil diperbarui.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        if ($asset->status === AssetStatus::InUse || $asset->hasActiveAssignment()) {
            return back()->with('error', 'Asset yang sedang IN_USE tidak dapat dihapus. Release terlebih dahulu.');
        }

        if ($asset->isUsedInProjects()) {
            $asset->status = AssetStatus::Inactive;
            $asset->save();

            return back()->with('success', 'Asset memiliki riwayat penggunaan. Status diubah menjadi INACTIVE.');
        }

        $asset->delete();

        return redirect()
            ->route('assets.index')
            ->with('success', 'Master asset berhasil dihapus.');
    }

    public function markAvailable(Asset $asset, MarkAssetAvailable $markAssetAvailable): RedirectResponse
    {
        $this->authorize('markAvailable', $asset);

        $markAssetAvailable->handle(request()->user(), $asset);

        return back()->with('success', 'Asset sekarang berstatus AVAILABLE.');
    }

    public function downloadRequestLetter(Asset $asset): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $asset);

        if (! $asset->request_letter_file || ! Storage::disk('public')->exists($asset->request_letter_file)) {
            return back()->with('error', 'File surat permohonan tidak ditemukan.');
        }

        return Storage::disk('public')->download($asset->request_letter_file);
    }

    public function import(Request $request, ImportAssets $importAssets): RedirectResponse
    {
        $this->authorize('create', Asset::class);

        $result = $importAssets->handle($request->user(), $request->file('file'));

        return back()->with(
            'success',
            "Import selesai. Dibuat: {$result['created']}, diperbarui: {$result['updated']}."
        );
    }

    public function export(ExportAssets $exportAssets): StreamedResponse
    {
        $this->authorize('viewAny', Asset::class);

        return $exportAssets->handle();
    }

    public function importTemplate(ExportAssets $exportAssets): StreamedResponse
    {
        $this->authorize('create', Asset::class);

        return $exportAssets->template();
    }
}
