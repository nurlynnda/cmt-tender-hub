<?php

namespace App\Actions\Pd;

use App\Models\{ProjectType, User};
use Illuminate\Support\Facades\Gate;

final class SaveProjectType
{
    public function handle(User $actor, ?ProjectType $type, string $name, int $marginBp, bool $active): ProjectType
    {
        Gate::forUser($actor)->authorize('manage-finance');
        $type ??= new ProjectType;
        $type->fill(['name' => trim($name), 'approved_margin_bp' => $marginBp, 'is_active' => $active])->save();

        return $type->fresh();
    }
}
