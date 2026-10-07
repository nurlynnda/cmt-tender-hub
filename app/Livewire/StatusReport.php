<?php

namespace App\Livewire;

use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Status')]
class StatusReport extends Component
{
    public function render()
    {
        return view('livewire.status-report');
    }
}
