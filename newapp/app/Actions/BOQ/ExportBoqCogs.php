<?php

namespace App\Actions\BOQ;

use App\Models\Boq;
use App\Models\User;
use App\Support\BoqCogsCsv;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportBoqCogs
{
    public function handle(User $actor, Boq $boq): StreamedResponse
    {
        $csv = BoqCogsCsv::fromBoq($boq);
        $filename = $boq->code.'-cogs.csv';

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo BoqCogsCsv::templateCsv();
        }, 'boq-cogs-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
