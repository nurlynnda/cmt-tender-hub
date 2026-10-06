<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{ActivityLog, Project, ProjectType, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateProjectDetails
{
    use GuardsProject;

    /** @param array{project_type_id:?int, start_date:?string, end_date:?string} $data */
    public function handle(User $actor, Project $project, int $expectedVersion, array $data): Project
    {
        $start = $data['start_date'] ?: null;
        $end = $data['end_date'] ?: null;
        if ($start && $end && $end < $start) {
            throw new InvalidArgumentException('The end date must be on or after the start date.');
        }

        return DB::transaction(function () use ($actor, $project, $expectedVersion, $data, $start, $end) {
            $p = $this->lockOpenProject($actor, $project);
            $this->checkProjectVersion($p, $expectedVersion);

            $type = $data['project_type_id'] ? ProjectType::findOrFail($data['project_type_id']) : null;
            if ($type && ! $type->is_active && $type->id !== $p->project_type_id) {
                throw new InvalidArgumentException('That project type is switched off.');
            }
            if ($type && $type->id !== $p->project_type_id) {
                $p->approved_margin_bp = $type->approved_margin_bp; // a new type brings its margin; same type keeps any adjustment
            }
            $p->forceFill([
                'project_type_id' => $type?->id,
                'start_date' => $start,
                'end_date' => $end,
                'updated_by' => $actor->id,
                'version' => $p->version + 1,
            ])->save();

            $fmt = fn (?string $d) => $d ? CarbonImmutable::parse($d)->format('d M Y') : '—';
            ActivityLog::record($p->tender, $actor, 'project_updated',
                'Project details updated — '.($type?->name ?? 'no type').', '.$fmt($start).' to '.$fmt($end));

            return $p->fresh();
        });
    }
}
