<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Exceptions\ProjectLocked;
use App\Models\{Project, User};
use App\Support\Percent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateProjectRates
{
    use GuardsProject;

    public function handle(User $actor, Project $project, int $expectedVersion, int $approvedBp, int $chargeBp, int $shareBp): Project
    {
        foreach ([$approvedBp, $chargeBp, $shareBp] as $bp) {
            if ($bp < 0 || $bp > 9999) {
                throw new InvalidArgumentException('Percentages must be from 0 to 99.99%.');
            }
        }

        return DB::transaction(function () use ($actor, $project, $expectedVersion, $approvedBp, $chargeBp, $shareBp) {
            $p = $this->lockManagedProject($actor, $project, $expectedVersion);
            if (! $p->isOpen()) {
                throw ProjectLocked::closed();
            }
            $p->forceFill([
                'approved_margin_bp' => $approvedBp,
                'project_charge_bp' => $chargeBp,
                'commission_share_bp' => $shareBp,
                'updated_by' => $actor->id,
                'version' => $p->version + 1,
            ])->save();

            $p->logActivity($actor, 'project_rates_changed', sprintf(
                'Project rates changed — approved margin %s, project charges %s, commission share %s',
                Percent::format($approvedBp), Percent::format($chargeBp), Percent::format($shareBp),
            ));

            return $p->fresh();
        });
    }
}
