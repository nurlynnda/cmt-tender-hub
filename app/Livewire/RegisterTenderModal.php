<?php

namespace App\Livewire;

use App\Actions\Tenders\{GenerateWoNumber, RegisterTender};
use App\Support\MalaysiaTime;
use App\Enums\{TenderCategory, TenderMode, TenderType};
use App\Exceptions\FieldCodeNotHeld;
use App\Livewire\Concerns\Toasts;
use App\Livewire\Forms\TenderForm;
use App\Models\{CollectedTender, Tender, User};
use App\Support\CompanyFieldCodes;
use Livewire\Attributes\On;
use Livewire\Component;

class RegisterTenderModal extends Component
{
    use Toasts;

    public TenderForm $form;
    public bool $open = false;
    public bool $confirmDuplicate = false;
    public array $duplicateWoNumbers = [];
    public ?int $collectedTenderId = null;
    /** Set when registering is refused because the company holds none of the tender's field codes; shows the pop-up (which offers Register anyway). */
    public ?array $codeBlock = null;
    /** The person chose "Register anyway" on the field-code pop-up; reset when the field codes are edited. */
    public bool $codeOverride = false;

    #[On('open-register-tender')]
    public function show(?int $collectedTenderId = null): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->confirmDuplicate = false;
        $this->duplicateWoNumbers = [];
        $this->collectedTenderId = null;
        $this->codeBlock = null;
        $this->codeOverride = false;
        if ($collectedTenderId !== null && ($c = CollectedTender::with(['sources', 'fieldCodes'])->find($collectedTenderId))) {
            $this->form->fillFromCollected($c);
            $this->collectedTenderId = $c->id;
        }
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function dismissCodeBlock(): void
    {
        $this->codeBlock = null;
    }

    /** The pop-up's "Register anyway": go ahead although none of the tender's field codes is one the company holds. */
    public function registerAnyway()
    {
        $this->codeOverride = true;
        $this->codeBlock = null;

        return $this->save();
    }

    public function updated(string $property): void
    {
        if ($property === 'form.fieldCodes') {
            $this->codeOverride = false;
        }
        if ($property === 'form.tenderCode') {
            $this->confirmDuplicate = false;
            $this->duplicateWoNumbers = [];
        }
    }

    public function save()
    {
        $this->validate(['collectedTenderId' => ['nullable', 'integer', 'exists:collected_tenders,id']]);

        // Checked first, right after the click, before anything else is validated or asked.
        if (! $this->codeOverride) {
            try {
                CompanyFieldCodes::assertHeld(CompanyFieldCodes::parse($this->form->fieldCodes));
            } catch (FieldCodeNotHeld $e) {
                $this->codeBlock = $e->tenderCodes;

                return null;
            }
        }

        $this->form->validate();

        if (! $this->confirmDuplicate) {
            $existing = Tender::where('tender_code', trim($this->form->tenderCode))->pluck('wo_number')->all();
            if ($existing !== []) {
                $this->duplicateWoNumbers = $existing;
                $this->confirmDuplicate = true;

                return null;
            }
        }

        $tender = app(RegisterTender::class)->handle(auth()->user(), [
            ...$this->form->toData(),
            'collected_tender_id' => $this->collectedTenderId,
        ], $this->codeOverride);

        $this->toastNextPage("Tender {$tender->wo_number} registered");

        return $this->redirectRoute('tenders.show', $tender);
    }

    public function render()
    {
        $today = MalaysiaTime::today();

        return view('livewire.register-tender-modal', [
            // Only a preview: the real number is taken when Register is pressed.
            'woPreview' => $this->open ? app(GenerateWoNumber::class)->peek($today) : null,
            'woDate' => $today,
            'people' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ministries' => config('tenderhub.ministries'),
            'modes' => TenderMode::cases(),
            'types' => TenderType::cases(),
            'categories' => TenderCategory::cases(),
        ]);
    }
}
