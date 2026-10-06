<?php

namespace App\Livewire;

use App\Actions\Pd\{DeleteProjectType, SaveFinanceDefaults, SaveProjectType};
use App\Models\{FinanceSetting, ProjectType};
use App\Rules\Percentage;
use App\Support\Percent;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Finance Settings')]
class FinanceSettings extends Component
{
    public ?int $editingType = null;
    public string $typeName = '';
    public string $typeMargin = '';
    public string $charge = '';
    public string $share = '';
    public ?string $notice = null;
    public ?string $problem = null;

    public function mount(): void
    {
        $this->authorize('manage-finance');
        $s = FinanceSetting::current();
        $this->charge = Percent::toInput($s->project_charge_bp);
        $this->share = Percent::toInput($s->commission_share_bp);
    }

    public function editType(int $id): void
    {
        $t = ProjectType::findOrFail($id);
        $this->editingType = $t->id;
        $this->typeName = $t->name;
        $this->typeMargin = Percent::toInput($t->approved_margin_bp);
        $this->resetValidation();
    }

    public function cancelType(): void
    {
        $this->reset('editingType', 'typeName', 'typeMargin');
    }

    public function saveType(): void
    {
        $this->validate([
            'typeName' => ['required', 'string', 'max:100', Rule::unique('project_types', 'name')->ignore($this->editingType)],
            'typeMargin' => ['required', new Percentage],
        ], [], ['typeName' => 'name', 'typeMargin' => 'approved margin']);
        $type = $this->editingType ? ProjectType::findOrFail($this->editingType) : null;
        app(SaveProjectType::class)->handle(auth()->user(), $type, $this->typeName, Percent::parseBp($this->typeMargin), $type?->is_active ?? true);
        $this->notice = 'Project type saved.';
        $this->cancelType();
    }

    public function toggleType(int $id): void
    {
        $t = ProjectType::findOrFail($id);
        app(SaveProjectType::class)->handle(auth()->user(), $t, $t->name, $t->approved_margin_bp, ! $t->is_active);
    }

    public function deleteType(int $id): void
    {
        $this->problem = null;
        try {
            app(DeleteProjectType::class)->handle(auth()->user(), ProjectType::findOrFail($id));
        } catch (DomainException $e) {
            $this->problem = $e->getMessage();
        }
    }

    public function saveDefaults(): void
    {
        $this->validate(['charge' => ['required', new Percentage], 'share' => ['required', new Percentage]], [],
            ['charge' => 'project charges', 'share' => 'commission share']);
        app(SaveFinanceDefaults::class)->handle(auth()->user(), Percent::parseBp($this->charge), Percent::parseBp($this->share));
        $this->notice = 'Saved. New projects will use these numbers.';
    }

    public function render()
    {
        return view('livewire.finance-settings', [
            'types' => ProjectType::withCount('projects')->orderBy('name')->get(),
        ]);
    }
}
