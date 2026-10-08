<?php

namespace App\Livewire;

use App\Actions\Users\{ChangeUserRole, CreateUser, SetUserActive, UpdateUserProfile};
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

    /** The account open in the Edit pop-up (null = closed). */
    public ?int $editingId = null;
    public string $editName = '';
    public string $editEmail = '';
    /** Empty = keep the current password. */
    public string $editPassword = '';

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

    public function edit(int $userId): void
    {
        $u = User::findOrFail($userId);
        $this->editingId = $u->id;
        $this->editName = $u->name;
        $this->editEmail = $u->email;
        $this->editPassword = '';
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editName', 'editEmail', 'editPassword');
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $target = User::findOrFail($this->editingId);
        $self = $target->is(auth()->user());
        $this->editEmail = strtolower(trim($this->editEmail));
        $this->validate([
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'editPassword' => $self ? ['nullable'] : ['nullable', 'string', 'min:8'],
        ], [], ['editName' => 'name', 'editEmail' => 'email', 'editPassword' => 'temporary password']);

        $password = $self || $this->editPassword === '' ? null : $this->editPassword; // your own password: Settings
        app(UpdateUserProfile::class)->handle(auth()->user(), $target, $this->editName, $this->editEmail, $password);
        $this->notice = "Saved — {$target->name}.".($password ? ' Share the temporary password with them privately.' : '');
        $this->cancelEdit();
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
