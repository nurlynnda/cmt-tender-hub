<?php

namespace App\Actions\Pd;

use App\Models\{ProjectType, User};
use DomainException;
use Illuminate\Support\Facades\Gate;

final class DeleteProjectType
{
    public function handle(User $actor, ProjectType $type): void
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $used = $type->projects()->count();
        if ($used > 0) {
            throw new DomainException("{$type->name} is used by {$used} ".($used === 1 ? 'project' : 'projects').' — switch it off instead.');
        }
        $type->delete();
    }
}
