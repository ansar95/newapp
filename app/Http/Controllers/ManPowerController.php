<?php

namespace App\Http\Controllers;

use App\Actions\ManPower\CreateManPower;
use App\Actions\ManPower\ImportManPowers;
use App\Actions\ManPower\UpdateManPower;
use App\Models\ManPower;
use App\Support\ManPowerCsv;
use App\Support\SpreadsheetFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManPowerController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ManPower::class);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $manPowers = ManPower::query()
            ->withCount('projectManPowers')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('man-powers.index', [
            'manPowers' => $manPowers,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function store(Request $request, CreateManPower $createManPower): RedirectResponse
    {
        $this->authorize('create', ManPower::class);

        $manPower = $createManPower->handle($request->user(), $request->all());

        return redirect()
            ->route('man-powers.index')
            ->with('success', 'Man power '.$manPower->code.' berhasil ditambahkan.');
    }

    public function import(Request $request, ImportManPowers $importManPowers): RedirectResponse
    {
        $this->authorize('create', ManPower::class);

        $result = $importManPowers->handle($request->user(), $request->file('file'));

        return redirect()
            ->route('man-powers.index')
            ->with('success', "Import man power selesai. Dibuat: {$result['created']}, diperbarui: {$result['updated']}.");
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', ManPower::class);

        return SpreadsheetFile::download(
            ManPowerCsv::rowsFromManPowers(),
            'man-power-export-'.now()->format('Ymd-His').'.xlsx',
            'Man Power'
        );
    }

    public function importTemplate(): StreamedResponse
    {
        $this->authorize('create', ManPower::class);

        return SpreadsheetFile::download(ManPowerCsv::templateRows(), 'man-power-import-template.xlsx', 'Man Power');
    }

    public function edit(ManPower $manPower): View
    {
        $this->authorize('update', $manPower);

        $manPower->load(['projectManPowers.project' => fn ($query) => $query->withTrashed()]);

        return view('man-powers.edit', [
            'manPower' => $manPower,
        ]);
    }

    public function update(Request $request, ManPower $manPower, UpdateManPower $updateManPower): RedirectResponse
    {
        $this->authorize('update', $manPower);

        $updateManPower->handle($manPower, $request->all());

        return redirect()
            ->route('man-powers.index')
            ->with('success', 'Man power '.$manPower->code.' berhasil diperbarui.');
    }
}
