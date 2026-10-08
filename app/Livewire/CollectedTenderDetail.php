<?php

namespace App\Livewire;

use App\Models\CollectedTender;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CollectedTenderDetail extends Component
{
    public CollectedTender $collectedTender;

    public function render()
    {
        $this->collectedTender->load(['sources', 'fieldCodes', 'pipelineTenders:id,wo_number,collected_tender_id']);

        return view('livewire.collected-tender-detail', ['t' => $this->collectedTender])
            ->title($this->collectedTender->reference_no ?: 'Collected tender');
    }
}
