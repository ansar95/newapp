<?php

namespace App\Actions\Asset;

use App\Support\AssetCsv;
use App\Support\SpreadsheetFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAssets
{
    public function handle(): StreamedResponse
    {
        return SpreadsheetFile::download(
            AssetCsv::rowsFromAssets(),
            'assets-export-'.now()->format('Ymd-His').'.xlsx',
            'Assets'
        );
    }

    public function template(): StreamedResponse
    {
        return SpreadsheetFile::download(AssetCsv::templateRows(), 'assets-import-template.xlsx', 'Assets');
    }
}
