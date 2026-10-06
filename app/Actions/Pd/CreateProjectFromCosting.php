<?php

namespace App\Actions\Pd;

use App\Enums\PdGroup;
use App\Models\{ActivityLog, FinanceSetting, Project, Tender, User};

/** Called inside the Award transaction. Does nothing if the tender already has a project (re-award). */
final class CreateProjectFromCosting
{
    public function handle(Tender $tender, User $actor): Project
    {
        if ($existing = $tender->project()->first()) {
            return $existing;
        }

        $defaults = FinanceSetting::current();
        $project = $tender->project()->create([
            'approved_margin_bp' => 0,
            'project_charge_bp' => $defaults->project_charge_bp,
            'commission_share_bp' => $defaults->commission_share_bp,
            'updated_by' => $actor->id,
            'version' => 1,
        ]);

        $summary = $tender->costingSummary();
        $position = 1;
        $project->lines()->create([
            'position' => $position++,
            'pd_group' => PdGroup::Collection,
            'name' => 'Contract value',
            'budget_sen' => $summary['bid_price_sen'] ?? $tender->submitted_price_sen ?? 0,
            'updated_by' => $actor->id,
        ]);
        foreach ($tender->costingLines as $i => $line) {
            $project->lines()->create([
                'position' => $position++,
                'pd_group' => $line->pd_group,
                'name' => mb_substr($line->description, 0, 255),
                'reference' => $line->vendor ? mb_substr($line->vendor, 0, 100) : null,
                'budget_sen' => $summary['lines'][$i]['line_cost_sen'], // monthly lines carry their whole cost
                'updated_by' => $actor->id,
            ]);
        }

        ActivityLog::record($tender, $actor, 'project_created', $summary ? 'Project created from the costing' : 'Project created');

        return $project;
    }
}
