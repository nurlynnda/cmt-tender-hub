<?php

namespace App\Actions\Quotations;

use App\Actions\Quotations\Concerns\GuardsQuotation;
use App\Costing\CostingCalculator;
use App\Enums\{PdGroup, QuotationStatus};
use App\Models\{ActivityLog, FinanceSetting, Project, Quotation, User};
use DomainException;
use Illuminate\Support\Facades\DB;

/** A PD for an accepted quotation: revenue = subtotal before SST; each costed item becomes a Principal cost line. */
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
            $position = 2;
            foreach ($q->items as $item) {
                $cost = CostingCalculator::line($item->costingLine())['line_cost_sen'];
                if ($cost === 0) {
                    continue; // nothing to budget
                }
                $project->lines()->create([
                    'position' => $position++, 'pd_group' => PdGroup::Principal, 'name' => mb_substr($item->title, 0, 255),
                    'reference' => $item->vendor ? mb_substr($item->vendor, 0, 100) : null, 'budget_sen' => $cost, 'updated_by' => $actor->id,
                ]);
            }
            ActivityLog::record($q, $actor, 'project_created', 'Project created from the quotation');

            return $project->fresh();
        });
    }
}
