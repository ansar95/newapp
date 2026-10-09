<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Enums\ProjectTermReadiness;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveProjectTerm
{
    public function __construct(private SyncProjectTermAmounts $syncProjectTermAmounts) {}

    /**
     * A term without any billing is deleted. A term whose billings are all cancelled is marked CANCELLED,
     * so the billing history stays intact.
     *
     * @return bool true when deleted, false when cancelled
     */
    public function handle(User $actor, ProjectTerm $term): bool
    {
        $project = $term->project;
        ClientBilling::assertProjectAcceptsBilling($project);

        if ($term->isCancelled()) {
            throw BusinessRuleException::make('Termin sudah dibatalkan.');
        }

        if ($term->billings()->where('status', '!=', ClientBillingStatus::Cancelled)->exists()) {
            throw BusinessRuleException::make('Termin masih memiliki penagihan aktif. Batalkan penagihan terlebih dahulu.');
        }

        return DB::transaction(function () use ($project, $term): bool {
            $this->syncProjectTermAmounts->handle($project, $project->contract_value, removedTermId: $term->id);

            if (! $term->billings()->exists()) {
                $term->delete();

                return true;
            }

            $term->readiness = ProjectTermReadiness::Cancelled;
            $term->save();

            return false;
        });
    }
}
