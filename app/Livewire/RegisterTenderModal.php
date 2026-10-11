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
    /** Set when registering is refused because CMT holds none of the tender's field codes; shows the pop-up. */
    public ?array $codeBlock = null;

    #[On('open-register-tender')]
    public function show(?int $collectedTenderId = null): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->confirmDuplicate = false;
        $this->duplicateWoNumbers = [];
        $this->collectedTenderId = null;
        $this->codeBlock = null;
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

    public function updated(string $property): void
    {
        if ($property === 'form.tenderCode') {
            $this->confirmDuplicate = false;
            $this->duplicateWoNumbers = [];
        }
    }

    public function save()
    {
        $this->validate(['collectedTenderId' => ['nullable', 'integer', 'exists:collected_tenders,id']]);
        $this->form->validate();

        // Checked before the duplicate prompt: there is no point asking about a tender we cannot register at all.
        try {
            CompanyFieldCodes::assertHeld(CompanyFieldCodes::parse($this->form->fieldCodes));
        } catch (FieldCodeNotHeld $e) {
            $this->codeBlock = $e->tenderCodes;

            return null;
        }

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
        ]);

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
