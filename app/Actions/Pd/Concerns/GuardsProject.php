<?php

namespace App\Actions\Pd\Concerns;

use App\Enums\TenderStatus;
use App\Exceptions\{ProjectLocked, StalePdRecord};
use App\Models\{PdLine, Project, User};
use Illuminate\Support\Facades\Gate;

trait GuardsProject
{
    /** Call inside DB::transaction. Locks the project row; the actor must be able to edit the tender. */
    private function lockOpenProject(User $actor, Project $project): Project
    {
        $p = Project::query()->lockForUpdate()->findOrFail($project->id);
        Gate::forUser($actor)->authorize('update', $p->tender);
        $this->requireAwarded($p);
        if (! $p->isOpen()) {
            throw ProjectLocked::closed();
        }

        return $p;
    }

    /** For per-project rates and close/reopen (Manager/Admin). Does not require the project to be open. */
    private function lockManagedProject(User $actor, Project $project, int $expectedVersion): Project
    {
        $p = Project::query()->lockForUpdate()->findOrFail($project->id);
        Gate::forUser($actor)->authorize('manage-projects');
        $this->requireAwarded($p);
        $this->checkProjectVersion($p, $expectedVersion);

        return $p;
    }

    private function checkProjectVersion(Project $locked, int $expected): void
    {
        if ($locked->version !== $expected) {
            throw StalePdRecord::project($locked);
        }
    }

    /** Locks the project, then the line; the line's version must match. Edits to other lines never conflict. */
    private function lockLine(User $actor, PdLine $line, int $expectedVersion): PdLine
    {
        $this->lockOpenProject($actor, $line->project);
        $l = PdLine::query()->lockForUpdate()->findOrFail($line->id);
        if ($l->version !== $expectedVersion) {
            throw StalePdRecord::line($l);
        }

        return $l;
    }

    private function requireAwarded(Project $p): void
    {
        if ($p->tender->status !== TenderStatus::Awarded) {
            throw ProjectLocked::notAwarded();
        }
    }
}
