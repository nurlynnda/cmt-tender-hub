<?php

namespace App\Actions\Pd;

use App\Actions\Pd\Concerns\GuardsProject;
use App\Models\{ActivityLog, PdLine, User};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdatePdLine
{
    use GuardsProject;

    /** @param array{name:string, reference:?string, budget_sen:int, scheduled_date:?string} $data */
    public function handle(User $actor, PdLine $line, int $expectedVersion, array $data): PdLine
    {
        $name = trim($data['name']);
        if ($name === '' || $data['budget_sen'] < 0) {
            throw new InvalidArgumentException('A line needs a name and a budget of RM 0.00 or more.');
        }

        return DB::transaction(function () use ($actor, $line, $expectedVersion, $data, $name) {
            $l = $this->lockLine($actor, $line, $expectedVersion);
            $l->forceFill([
                'name' => mb_substr($name, 0, 255),
                'reference' => trim((string) $data['reference']) ?: null,
                'budget_sen' => $data['budget_sen'],
                'scheduled_date' => $l->pd_group->isCollection() ? ($data['scheduled_date'] ?: null) : null,
                'updated_by' => $actor->id,
                'version' => $l->version + 1,
            ])->save();
            ActivityLog::record($l->project->tender, $actor, 'pd_line_updated',
                "PD line updated — {$l->pd_group->label()}: {$l->name}, budget ".Money::format($l->budget_sen));

            return $l->fresh();
        });
    }
}
