<?php

namespace App\Livewire;

use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Settings')]
class Settings extends Component
{
    public string $name = '';
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPassword_confirmation = '';
    public ?string $status = null;

    public function mount(): void
    {
        $this->name = auth()->user()->name;
    }

    public function saveProfile(): void
    {
        $this->validate(['name' => ['required', 'string', 'max:255']]);
        auth()->user()->update(['name' => trim($this->name)]);
        $this->status = 'Profile saved.';
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'newPassword' => ['required', 'min:8', 'confirmed'],
        ]);
        auth()->user()->update(['password' => $this->newPassword]);
        $this->reset('currentPassword', 'newPassword', 'newPassword_confirmation');
        $this->status = 'Password changed.';
    }

    public function render()
    {
        return view('livewire.settings');
    }
}
