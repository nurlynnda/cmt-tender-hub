<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Forgot password')]
class ForgotPassword extends Component
{
    public string $email = '';
    public ?string $status = null;

    public function send(): void
    {
        $this->validate(['email' => 'required|email']);
        Password::sendResetLink(['email' => $this->email]);

        // Same message either way, so nobody can use this form to discover who has an account.
        $this->status = 'If that email belongs to an account, a reset link is on its way.';
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
