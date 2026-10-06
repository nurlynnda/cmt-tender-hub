<?php

namespace App\Livewire;

use App\Actions\Pd\{AddPdLine, CloseProject, RemovePdEntry, RemovePdLine, ReopenProject, SavePdEntry, UpdatePdLine, UpdateProjectDetails, UpdateProjectRates};
use App\Enums\{PdEntryType, PdGroup};
use App\Exceptions\{ProjectLocked, StalePdRecord};
use App\Models\{PdEntry, PdLine, Project, ProjectType, Tender};
use App\Rules\{MoneyAmount, Percentage};
use App\Support\{Money, Percent};
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;

/** The PD tab. Every edit is saved straight away; only edits to the same line can conflict. */
class TenderPd extends Component
{
    public Tender $tender;
    public string $group = 'collection';
    /** On-screen line fields, keyed "l{id}". */
    public array $rows = [];
    public array $versions = [];
    public array $header = [];
    public array $rates = [];
    public int $projectVersion = 1;
    public ?int $openLine = null;
    public array $entry = [];
    public ?int $editingEntry = null;
    public ?string $problem = null;

    public function mount(): void
    {
        $this->loadProject();
        $this->resetEntry();
    }

    private function project(): Project
    {
        return $this->tender->project()->firstOrFail();
    }

    private function loadProject(): void
    {
        $p = $this->project();
        $this->projectVersion = $p->version;
        $this->header = [
            'project_type_id' => (string) ($p->project_type_id ?? ''),
            'start_date' => $p->start_date?->format('Y-m-d') ?? '',
            'end_date' => $p->end_date?->format('Y-m-d') ?? '',
        ];
        $this->rates = [
            'approved' => Percent::toInput($p->approved_margin_bp),
            'charge' => Percent::toInput($p->project_charge_bp),
            'share' => Percent::toInput($p->commission_share_bp),
        ];
        foreach ($p->lines as $l) {
            $this->loadLine($l);
        }
    }

    private function loadLine(PdLine $l): void
    {
        $this->rows["l{$l->id}"] = [
            'name' => $l->name,
            'reference' => (string) $l->reference,
            'budget' => Money::toInput($l->budget_sen),
            'scheduled_date' => $l->scheduled_date?->format('Y-m-d') ?? '',
        ];
        $this->versions["l{$l->id}"] = $l->version;
    }

    private function resetEntry(): void
    {
        $this->editingEntry = null;
        $this->entry = ['type' => '', 'number' => '', 'date' => '', 'amount' => '', 'note' => ''];
    }

    public function updated(string $property): void
    {
        $parts = explode('.', $property);
        match ($parts[0]) {
            'rows' => isset($parts[1]) ? $this->saveLine((int) substr($parts[1], 1)) : null,
            'header' => $this->saveHeader(),
            'rates' => $this->saveRates(),
            default => null,
        };
    }

    public function selectGroup(string $group): void
    {
        $this->group = PdGroup::tryFrom($group)?->value ?? PdGroup::Collection->value;
        $this->openLine = null;
        $this->resetEntry();
    }

    private function saveLine(int $id): void
    {
        $key = "l{$id}";
        $this->validate([
            "rows.$key.name" => ['required', 'string', 'max:255'],
            "rows.$key.reference" => ['nullable', 'string', 'max:100'],
            "rows.$key.budget" => ['required', new MoneyAmount],
            "rows.$key.scheduled_date" => ['nullable', 'date_format:Y-m-d'],
        ], [], [
            "rows.$key.name" => 'name', "rows.$key.reference" => 'reference',
            "rows.$key.budget" => 'budget', "rows.$key.scheduled_date" => 'scheduled date',
        ]);
        $row = $this->rows[$key];
        $this->run(function () use ($id, $key, $row) {
            $line = app(UpdatePdLine::class)->handle(auth()->user(), $this->line($id), $this->versions[$key] ?? 0, [
                'name' => $row['name'],
                'reference' => $row['reference'],
                'budget_sen' => Money::parse($row['budget']) ?? 0,
                'scheduled_date' => $row['scheduled_date'] ?: null,
            ]);
            $this->versions[$key] = $line->version;
        });
    }

    private function saveHeader(): void
    {
        $this->validate([
            'header.project_type_id' => ['nullable', Rule::exists('project_types', 'id')],
            'header.start_date' => ['nullable', 'date_format:Y-m-d'],
            // Only compare with the start date when there is one; an end date alone is fine.
            'header.end_date' => ['nullable', 'date_format:Y-m-d', Rule::when(($this->header['start_date'] ?? '') !== '', 'after_or_equal:header.start_date')],
        ], [], ['header.project_type_id' => 'project type', 'header.start_date' => 'start date', 'header.end_date' => 'end date']);
        $this->run(function () {
            $p = app(UpdateProjectDetails::class)->handle(auth()->user(), $this->project(), $this->projectVersion, [
                'project_type_id' => $this->header['project_type_id'] !== '' ? (int) $this->header['project_type_id'] : null,
                'start_date' => $this->header['start_date'] ?: null,
                'end_date' => $this->header['end_date'] ?: null,
            ]);
            $this->projectVersion = $p->version;
            $this->rates['approved'] = Percent::toInput($p->approved_margin_bp);
        });
    }

    private function saveRates(): void
    {
        $this->authorize('manage-projects');
        $this->validate([
            'rates.approved' => ['required', new Percentage],
            'rates.charge' => ['required', new Percentage],
            'rates.share' => ['required', new Percentage],
        ], [], ['rates.approved' => 'approved margin', 'rates.charge' => 'project charges', 'rates.share' => 'commission share']);
        $this->run(function () {
            $p = app(UpdateProjectRates::class)->handle(auth()->user(), $this->project(), $this->projectVersion,
                Percent::parseBp($this->rates['approved']), Percent::parseBp($this->rates['charge']), Percent::parseBp($this->rates['share']));
            $this->projectVersion = $p->version;
        });
    }

    public function addLine(): void
    {
        $this->run(fn () => $this->loadLine(app(AddPdLine::class)->handle(auth()->user(), $this->project(), PdGroup::from($this->group))));
    }

    public function removeLine(int $id): void
    {
        $this->run(function () use ($id) {
            app(RemovePdLine::class)->handle(auth()->user(), $this->line($id), $this->versions["l{$id}"] ?? 0);
            unset($this->rows["l{$id}"], $this->versions["l{$id}"]);
            if ($this->openLine === $id) {
                $this->openLine = null;
            }
        });
    }

    public function openDocuments(int $id): void
    {
        $this->openLine = $this->openLine === $id ? null : $id;
        $this->resetEntry();
        $this->resetValidation();
    }

    public function editEntry(int $id): void
    {
        $this->run(function () use ($id) {
            $e = $this->entryOf($id);
            $this->openLine = $e->pd_line_id;
            $this->editingEntry = $e->id;
            $this->entry = [
                'type' => $e->type->value, 'number' => (string) $e->number, 'date' => $e->date->format('Y-m-d'),
                'amount' => Money::toInput($e->amount_sen), 'note' => (string) $e->note,
            ];
        });
    }

    public function cancelEntry(): void
    {
        $this->resetEntry();
        $this->resetValidation();
    }

    public function saveEntry(): void
    {
        $this->run(function () {
            $line = $this->line($this->openLine ?? 0);
            $this->validate([
                'entry.type' => ['required', Rule::in(array_map(fn (PdEntryType $t) => $t->value, $line->pd_group->entryTypes()))],
                'entry.number' => ['nullable', 'string', 'max:100'],
                'entry.date' => ['required', 'date_format:Y-m-d'],
                'entry.amount' => ['required', new MoneyAmount(mustBePositive: true)],
                'entry.note' => ['nullable', 'string', 'max:255'],
            ], ['entry.type.in' => 'Choose a document type this line takes.'], [
                'entry.type' => 'type', 'entry.number' => 'number', 'entry.date' => 'date', 'entry.amount' => 'amount', 'entry.note' => 'note',
            ]);
            app(SavePdEntry::class)->handle(auth()->user(), $line, $this->versions["l{$line->id}"] ?? 0,
                $this->editingEntry ? $this->entryOf($this->editingEntry) : null, [
                    'type' => $this->entry['type'],
                    'number' => $this->entry['number'],
                    'date' => $this->entry['date'],
                    'amount_sen' => Money::parse($this->entry['amount']),
                    'note' => $this->entry['note'],
                ]);
            $this->versions["l{$line->id}"] = $line->fresh()->version;
            $this->resetEntry();
        });
    }

    public function removeEntry(int $id): void
    {
        $this->run(function () use ($id) {
            $e = $this->entryOf($id);
            app(RemovePdEntry::class)->handle(auth()->user(), $e, $this->versions["l{$e->pd_line_id}"] ?? 0);
            $this->versions["l{$e->pd_line_id}"] = PdLine::findOrFail($e->pd_line_id)->version;
            if ($this->editingEntry === $e->id) {
                $this->resetEntry();
            }
        });
    }

    public function closeProject(): void
    {
        $this->run(fn () => $this->projectVersion = app(CloseProject::class)->handle(auth()->user(), $this->project(), $this->projectVersion)->version);
    }

    public function reopenProject(): void
    {
        $this->run(fn () => $this->projectVersion = app(ReopenProject::class)->handle(auth()->user(), $this->project(), $this->projectVersion)->version);
    }

    /** A line of THIS project only; a missing or foreign id is treated as removed. */
    private function line(int $id): PdLine
    {
        return PdLine::where('project_id', $this->project()->id)->findOrFail($id);
    }

    private function entryOf(int $id): PdEntry
    {
        return PdEntry::whereHas('line', fn ($q) => $q->where('project_id', $this->project()->id))->findOrFail($id);
    }

    /** Runs an action; refusals become a message and typed values stay on screen. */
    private function run(callable $action): void
    {
        $this->problem = null;
        try {
            $action();
        } catch (ModelNotFoundException) {
            $this->problem = 'This line was removed by someone else — reload to see the latest.';
        } catch (StalePdRecord|ProjectLocked|DomainException $e) {
            $this->problem = $e->getMessage();
        } catch (InvalidArgumentException $e) {
            $this->addError('form', $e->getMessage());
        }
    }

    public function render()
    {
        $project = $this->project()->load(['projectType', 'closedBy']);
        foreach ($project->lines as $l) {
            if (! isset($this->rows["l{$l->id}"])) {
                $this->loadLine($l); // added by a colleague since this tab opened
            }
        }
        $summary = $project->summary();

        return view('livewire.tender-pd', [
            'project' => $project,
            'summary' => $summary,
            'groupLines' => array_values(array_filter($summary['lines'], fn ($l) => $l['group'] === $this->group)),
            'counts' => array_count_values(array_column($summary['lines'], 'group')),
            'groupEnum' => PdGroup::from($this->group),
            'entries' => $this->openLine ? PdEntry::where('pd_line_id', $this->openLine)->orderBy('date')->orderBy('id')->get() : collect(),
            'types' => ProjectType::where('is_active', true)->orWhere('id', $project->project_type_id)->orderBy('name')->get(),
            'canEdit' => Gate::allows('update', $this->tender) && $project->isOpen(),
            'canManage' => Gate::allows('manage-projects'),
        ]);
    }
}
