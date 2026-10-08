<?php

namespace App\Livewire;

use App\Actions\Company\{RemoveCompanyStamp, SaveCompanyProfile, SaveCompanyStamp};
use App\Actions\Pd\{DeleteProjectType, SaveFinanceDefaults, SaveProjectType};
use App\Models\{CompanyProfile, FinanceSetting, ProjectType};
use App\Rules\Percentage;
use App\Support\Percent;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Finance Settings')]
class FinanceSettings extends Component
{
    use WithFileUploads;

    /** Letterhead fields (Company letterhead section); "sst" is the default SST % as typed. */
    public array $company = [];
    public $stamp = null;
    public ?int $editingType = null;
    public string $typeName = '';
    public string $typeMargin = '';
    public string $charge = '';
    public string $share = '';
    /** Our company names in tender results, one per line (to spot our wins). */
    public string $ownNames = '';
    public ?string $notice = null;
    public ?string $problem = null;

    public function mount(): void
    {
        $this->authorize('manage-finance');
        $s = FinanceSetting::current();
        $this->charge = Percent::toInput($s->project_charge_bp);
        $this->share = Percent::toInput($s->commission_share_bp);
        $this->ownNames = (string) $s->own_company_names;
        $this->loadCompany();
    }

    private function loadCompany(): void
    {
        $c = CompanyProfile::current();
        $this->company = [
            'name' => $c->name, 'registration_no' => (string) $c->registration_no, 'sst_no' => (string) $c->sst_no,
            'address' => (string) $c->address, 'phone' => (string) $c->phone, 'email' => (string) $c->email,
            'website' => (string) $c->website, 'default_terms' => (string) $c->default_terms,
            'sst' => Percent::toInput($c->default_sst_bp),
        ];
    }

    public function saveCompany(): void
    {
        $this->validate([
            'company.name' => ['required', 'string', 'max:255'],
            'company.registration_no' => ['nullable', 'string', 'max:255'],
            'company.sst_no' => ['nullable', 'string', 'max:255'],
            'company.address' => ['nullable', 'string', 'max:1000'],
            'company.phone' => ['nullable', 'string', 'max:50'],
            'company.email' => ['nullable', 'email', 'max:255'],
            'company.website' => ['nullable', 'string', 'max:255'],
            'company.default_terms' => ['nullable', 'string', 'max:5000'],
            'company.sst' => ['required', new Percentage],
        ], [], ['company.name' => 'company name', 'company.email' => 'email', 'company.sst' => 'default SST']);
        $fields = array_map(fn ($v) => trim((string) $v) === '' ? null : $v, array_diff_key($this->company, ['sst' => true]));
        app(SaveCompanyProfile::class)->handle(auth()->user(), [...$fields, 'default_sst_bp' => Percent::parseBp($this->company['sst'])]);
        $this->notice = 'Letterhead saved. New quotations will use it.';
    }

    public function uploadStamp(): void
    {
        $this->validate(['stamp' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:1024']],
            ['stamp.*' => 'Upload a PNG or JPG image of 1 MB or less.']);
        app(SaveCompanyStamp::class)->handle(auth()->user(), $this->stamp);
        $this->reset('stamp');
        $this->notice = 'Stamp saved. New quotations will use it.';
    }

    public function removeStamp(): void
    {
        app(RemoveCompanyStamp::class)->handle(auth()->user());
        $this->notice = 'Stamp removed. Quotations already created keep theirs.';
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

    public function saveOwnNames(): void
    {
        $this->authorize('manage-finance');
        $this->validate(['ownNames' => ['nullable', 'string', 'max:2000']], [], ['ownNames' => 'company names']);
        FinanceSetting::current()->forceFill(['own_company_names' => trim($this->ownNames)])->save();
        $this->notice = 'Saved. Market Insights and Find Tenders will mark these names as ours.';
    }

    public function render()
    {
        return view('livewire.finance-settings', [
            'types' => ProjectType::withCount('projects')->orderBy('name')->get(),
            'stampPath' => CompanyProfile::current()->stamp_path,
        ]);
    }
}
