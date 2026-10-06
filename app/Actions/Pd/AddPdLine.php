<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Enums\PdGroup;
use App\Models\{ActivityLog, PdLine, Project, User};
use Illuminate\Support\Facades\DB;

final class AddPdLine
{
    use GuardsProject;

    public function handle(User $actor, Project $project, PdGroup $group): PdLine
    {
        return DB::transaction(function () use ($actor, $project, $group) {
            $p = $this->lockOpenProject($actor, $project);
            $line = $p->lines()->create([
                'position' => (int) PdLine::where('project_id', $p->id)->max('position') + 1,
                'pd_group' => $group,
                'name' => 'New line',
                'budget_sen' => 0,
                'updated_by' => $actor->id,
                'version' => 1,
            ]);
            ActivityLog::record($p->tender, $actor, 'pd_line_added', "PD line added — {$group->label()}");

            return $line->fresh();
        });
    }
}
