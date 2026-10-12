<?php

namespace App\Actions\BOQ;

use App\Models\Boq;
use App\Models\User;
use App\Support\BoqCogsCsv;
use App\Support\SpreadsheetFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportBoqCogs
{
    public function handle(User $actor, Boq $boq): StreamedResponse
    {
        return SpreadsheetFile::download(BoqCogsCsv::rowsFromBoq($boq), $boq->code.'-cogs.xlsx', 'COGS');
    }

    public function template(): StreamedResponse
    {
        return SpreadsheetFile::download(BoqCogsCsv::templateRows(), 'boq-cogs-template.xlsx', 'COGS');
    }
}
