<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Enums\{PdGroup, QuotationStatus};
use App\Models\{ActivityLog, FinanceSetting, Project, Quotation, User};
use DomainException;
use Illuminate\Support\Facades\DB;

/** A PD for an accepted quotation: revenue = subtotal before SST; cost lines are added by hand. */
final class CreateProjectFromQuotation
{
    use GuardsQuotation;

    public function handle(User $actor, Quotation $quotation, int $expectedVersion): Project
    {
        return DB::transaction(function () use ($actor, $quotation, $expectedVersion) {
            $q = $this->lockQuotation($actor, $quotation, $expectedVersion);
            $this->requireStatus($q, [QuotationStatus::Accepted], 'turned into a project');
            if ($q->project()->exists()) {
                throw new DomainException('This quotation already has a project.');
            }
            $defaults = FinanceSetting::current();
            $project = Project::create([
                'quotation_id' => $q->id,
                'approved_margin_bp' => 0,
                'project_charge_bp' => $defaults->project_charge_bp,
                'commission_share_bp' => $defaults->commission_share_bp,
                'updated_by' => $actor->id,
                'version' => 1,
            ]);
            $project->lines()->create([
                'position' => 1, 'pd_group' => PdGroup::Collection, 'name' => 'Contract value',
                'budget_sen' => $q->totals()['subtotal_sen'], 'updated_by' => $actor->id,
            ]);
            ActivityLog::record($q, $actor, 'project_created', 'Project created from the quotation');

            return $project->fresh();
        });
    }
}
