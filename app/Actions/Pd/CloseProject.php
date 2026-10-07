<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Exceptions\ProjectLocked;
use App\Models\{Project, User};
use Illuminate\Support\Facades\DB;

final class CloseProject
{
    use GuardsProject;

    public function handle(User $actor, Project $project, int $expectedVersion): Project
    {
        return DB::transaction(function () use ($actor, $project, $expectedVersion) {
            $p = $this->lockManagedProject($actor, $project, $expectedVersion);
            if (! $p->isOpen()) {
                throw ProjectLocked::closed();
            }
            $p->forceFill(['closed_at' => now(), 'closed_by' => $actor->id, 'updated_by' => $actor->id, 'version' => $p->version + 1])->save();
            $p->logActivity($actor, 'project_closed', 'Project closed');

            return $p->fresh();
        });
    }
}
