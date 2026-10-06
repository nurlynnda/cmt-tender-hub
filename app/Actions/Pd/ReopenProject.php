<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{ActivityLog, Project, User};
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReopenProject
{
    use GuardsProject;

    public function handle(User $actor, Project $project, int $expectedVersion): Project
    {
        return DB::transaction(function () use ($actor, $project, $expectedVersion) {
            $p = $this->lockManagedProject($actor, $project, $expectedVersion);
            if ($p->isOpen()) {
                throw new InvalidArgumentException('This project is already open.');
            }
            $p->forceFill(['closed_at' => null, 'closed_by' => null, 'updated_by' => $actor->id, 'version' => $p->version + 1])->save();
            ActivityLog::record($p->tender, $actor, 'project_reopened', 'Project reopened');

            return $p->fresh();
        });
    }
}
