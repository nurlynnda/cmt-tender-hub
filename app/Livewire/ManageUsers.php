<?php

namespace App\Livewire;

use App\Actions\Users\{ChangeUserRole, CreateUser, SetUserActive};
use App\Enums\Role;
use App\Models\User;
use DomainException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\{Layout, Title};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Manage Users')]
class ManageUsers extends Component
{
    public string $name = '';
    public string $email = '';
    public string $role = 'staff';
    public string $password = '';
    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('manage-users');
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', 'min:8'],
        ]);

        app(CreateUser::class)->handle(auth()->user(), $this->name, $this->email, Role::from($this->role), $this->password);
        $this->notice = "Account created for {$this->name}. Share the temporary password with them privately.";
        $this->reset('name', 'email', 'role', 'password');
    }

    public function changeRole(int $userId, string $role): void
    {
        $this->attempt(fn () => app(ChangeUserRole::class)->handle(auth()->user(), User::findOrFail($userId), Role::from($role)));
    }

    public function toggleActive(int $userId): void
    {
        $target = User::findOrFail($userId);
        $this->attempt(fn () => app(SetUserActive::class)->handle(auth()->user(), $target, ! $target->is_active));
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
            $this->notice = null;
        } catch (DomainException $e) {
            $this->notice = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.manage-users', [
            'users' => User::orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }
}
