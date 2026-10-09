<?php

namespace App\Actions\Asset;

use App\Support\AssetCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAssets
{
    public function handle(): StreamedResponse
    {
        $csv = AssetCsv::fromAssets();

        return response()->streamDownload(
            function () use ($csv): void {
                echo $csv;
            },
            'assets-export-'.now()->format('Ymd-His').'.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(
            function (): void {
                echo AssetCsv::templateCsv();
            },
            'assets-import-template.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }
}
