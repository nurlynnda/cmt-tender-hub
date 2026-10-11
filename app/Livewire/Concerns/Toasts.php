<?php

namespace App\Livewire\Concerns;

/** Small confirmation pop-ups ("Tender registered") shown by <x-toaster> in the page layout. */
trait Toasts
{
    /** Shows a pop-up on the current page. $type is success | error | info. */
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }

    /** Shows a pop-up on the next page — use just before a redirect. */
    protected function toastNextPage(string $message, string $type = 'success'): void
    {
        session()->flash('toast', ['message' => $message, 'type' => $type]);
    }
}
