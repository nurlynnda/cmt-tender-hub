<?php

namespace App\Livewire;

use App\Models\Quotation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class QuotationPage extends Component
{
    public Quotation $quotation;

    public function render()
    {
        return view('livewire.quotation-page')->title($this->quotation->number);
    }
}
