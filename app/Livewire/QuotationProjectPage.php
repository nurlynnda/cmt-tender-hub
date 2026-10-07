<?php

namespace App\Livewire;

use App\Models\Quotation;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** The PD of a project created from an accepted quotation. */
#[Layout('layouts.app')]
class QuotationProjectPage extends Component
{
    public Quotation $quotation;

    public function mount(Quotation $quotation): void
    {
        abort_unless($quotation->project()->exists(), 404);
        $this->quotation = $quotation;
    }

    public function render()
    {
        return view('livewire.quotation-project-page', ['project' => $this->quotation->project])
            ->title($this->quotation->number.' · Project');
    }
}
