<?php

namespace App\Actions\SCurve;

use App\Enums\SCurveKind;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\SCurveBaseline;
use App\Models\User;
use App\Support\SCurvePlanSheet;
use App\Support\SpreadsheetFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ImportSCurveItemPlan
{
    public function __construct(private EstablishSCurveFromItems $establishSCurveFromItems) {}

    /**
     * Reads item start and finish dates from the Excel template and establishes that plan.
     */
    public function handle(User $actor, Project $project, mixed $file, SCurveKind $kind, ?string $reason = null): SCurveBaseline
    {
        Validator::make(
            ['file' => $file],
            ['file' => SpreadsheetFile::UPLOAD_RULES],
            SpreadsheetFile::uploadMessages(),
        )->validate();

        try {
            $rows = SpreadsheetFile::rows($file);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $project->loadMissing('approvedBoqs.cogs.items');
        $items = $project->approvedBoqs->flatMap(fn ($boq) => $boq->cogs->flatMap->items);

        if ($items->isEmpty() || $project->totalCost() <= 0) {
            throw BusinessRuleException::make('Rencana awal membutuhkan BOQ berstatus APPROVED yang memiliki nilai.');
        }

        try {
            $dates = SCurvePlanSheet::dates($rows, $items);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return $this->establishSCurveFromItems->handle($actor, $project, $kind, $dates, $reason);
    }
}
