<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{ActivityLog, PdLine, User};
use DomainException;
use Illuminate\Support\Facades\DB;

final class RemovePdLine
{
    use GuardsProject;

    public function handle(User $actor, PdLine $line, int $expectedVersion): void
    {
        DB::transaction(function () use ($actor, $line, $expectedVersion) {
            $l = $this->lockLine($actor, $line, $expectedVersion);
            if ($l->entries()->exists()) {
                throw new DomainException("Remove this line's documents first.");
            }
            $tender = $l->project->tender;
            $l->delete();
            ActivityLog::record($tender, $actor, 'pd_line_removed', "PD line removed — {$l->pd_group->label()}: {$l->name}");
        });
    }
}
