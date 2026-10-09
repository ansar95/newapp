<?php

namespace App\Http\Controllers;

use App\Actions\Bmhp\ImportMasterBmhps;
use App\Actions\Bmhp\ParseMasterBmhpImport;
use App\Exceptions\BusinessRuleException;
use App\Models\MasterBmhp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterBmhpImportController extends Controller
{
    /**
     * Parsed rows wait in the session between preview and confirmation.
     */
    private const SESSION_KEY = 'master_bmhp_import';

    public function show(Request $request): View
    {
        $this->authorize('import', MasterBmhp::class);

        $preview = $request->session()->get(self::SESSION_KEY);
        $rows = collect($preview['rows'] ?? []);

        return view('master-bmhps.import', [
            'preview' => $preview,
            'rows' => $rows,
            'counts' => [
                'create' => $rows->where('action', 'create')->count(),
                'update' => $rows->where('action', 'update')->count(),
                'unchanged' => $rows->where('action', 'unchanged')->count(),
                'error' => $rows->where('action', 'error')->count(),
            ],
            'categories' => MasterBmhp::categoryOptions(),
        ]);
    }

    public function template(): StreamedResponse
    {
        $this->authorize('import', MasterBmhp::class);

        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fwrite($output, "sep=;\n");
            fputcsv($output, ParseMasterBmhpImport::COLUMNS, ';', '"', '');
            fputcsv($output, ['', 'Kabel UTP Cat6', 'Material', 'Roll', 'Kabel jaringan 305 meter', 'ACTIVE'], ';', '"', '');
            fputcsv($output, ['', 'Sarung Tangan Nitril', 'Consumable', 'Box', '', ''], ';', '"', '');
            fclose($output);
        }, 'template-import-master-bmhp.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request, ParseMasterBmhpImport $parseMasterBmhpImport): RedirectResponse
    {
        $this->authorize('import', MasterBmhp::class);

        $request->session()->forget(self::SESSION_KEY);

        $file = $request->file('file');
        $rows = $parseMasterBmhpImport->handle($file);

        $request->session()->put(self::SESSION_KEY, [
            'token' => (string) Str::uuid(),
            'filename' => $file->getClientOriginalName(),
            'rows' => $rows,
        ]);

        return redirect()->route('master-bmhps.import');
    }

    public function confirm(Request $request, ImportMasterBmhps $importMasterBmhps): RedirectResponse
    {
        $this->authorize('import', MasterBmhp::class);

        $preview = $request->session()->get(self::SESSION_KEY);

        if ($preview === null || ! hash_equals($preview['token'], (string) $request->input('token'))) {
            throw BusinessRuleException::make('Preview import sudah kedaluwarsa. Upload ulang file CSV.');
        }

        $result = $importMasterBmhps->handle($request->user(), $preview['rows']);
        $request->session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('master-bmhps.index')
            ->with('success', "Import selesai: {$result['created']} BMHP baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati.");
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->authorize('import', MasterBmhp::class);

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('master-bmhps.import');
    }
}
