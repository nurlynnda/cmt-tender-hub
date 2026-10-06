<?php

namespace App\Livewire;

use App\Actions\Tenders\RegisterTender;
use App\Enums\{TenderCategory, TenderMode, TenderType};
use App\Livewire\Forms\TenderForm;
use App\Models\{CollectedTender, Tender, User};
use Livewire\Attributes\On;
use Livewire\Component;

class RegisterTenderModal extends Component
{
    public TenderForm $form;
    public bool $open = false;
    public bool $confirmDuplicate = false;
    public array $duplicateWoNumbers = [];
    public ?int $collectedTenderId = null;

    #[On('open-register-tender')]
    public function show(?int $collectedTenderId = null): void
    {
        $this->form->reset();
        $this->resetValidation();
        $this->confirmDuplicate = false;
        $this->duplicateWoNumbers = [];
        $this->collectedTenderId = null;
        if ($collectedTenderId !== null && ($c = CollectedTender::with('sources')->find($collectedTenderId))) {
            $this->form->fillFromCollected($c);
            $this->collectedTenderId = $c->id;
        }
        $this->open = true;
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

        return $this->redirectRoute('tenders.show', $tender);
    }

    public function render()
    {
        return view('livewire.register-tender-modal', [
            'people' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ministries' => config('tenderhub.ministries'),
            'modes' => TenderMode::cases(),
            'types' => TenderType::cases(),
            'categories' => TenderCategory::cases(),
        ]);
    }
}
