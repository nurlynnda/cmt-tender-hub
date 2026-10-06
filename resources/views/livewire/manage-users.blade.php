@php $input = 'rounded-lg border border-line bg-surface px-3 py-2 text-sm'; @endphp
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-semibold">Manage Users</h1>
        <p class="text-sm text-muted">Add staff accounts, change roles, deactivate leavers</p>
    </header>
    @if ($notice) <p class="rounded-lg bg-warn-bg px-3 py-2 text-sm text-warn-ink">{{ $notice }}</p> @endif

    <form wire:submit="create" class="grid gap-3 rounded-xl border border-line bg-surface p-4 sm:grid-cols-5">
        <div><input wire:model="name" placeholder="Full name" class="{{ $input }} w-full">@error('name')<p class="text-xs text-bad-ink">{{ $message }}</p>@enderror</div>
        <div><input wire:model="email" type="email" placeholder="Work email" class="{{ $input }} w-full">@error('email')<p class="text-xs text-bad-ink">{{ $message }}</p>@enderror</div>
        <select wire:model="role" class="{{ $input }}" aria-label="Role for new user">
            @foreach ($roles as $r) <option value="{{ $r->value }}">{{ $r->label() }}</option> @endforeach
        </select>
        <div><input wire:model="password" type="text" placeholder="Temporary password" class="{{ $input }} w-full">@error('password')<p class="text-xs text-bad-ink">{{ $message }}</p>@enderror</div>
        <button class="rounded-lg bg-chip px-4 py-2 text-sm font-medium text-chip-ink">Add user</button>
    </form>

    <div class="overflow-x-auto rounded-xl border border-line bg-surface">
        <table class="w-full min-w-[600px] text-sm">
            <thead class="bg-subtle text-left text-xs uppercase tracking-wide text-muted">
                <tr><th class="px-3 py-2">Name</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">Role</th><th class="px-3 py-2">Status</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($users as $u)
                <tr wire:key="user-{{ $u->id }}" class="border-t border-line">
                    <td class="px-3 py-2"><div class="flex items-center gap-2"><x-avatar :user="$u" /> {{ $u->name }}</div></td>
                    <td class="px-3 py-2">{{ $u->email }}</td>
                    <td class="px-3 py-2">
                        <select wire:change="changeRole({{ $u->id }}, $event.target.value)" class="{{ $input }} py-1" aria-label="Role for {{ $u->name }}">
                            @foreach ($roles as $r) <option value="{{ $r->value }}" @selected($u->role === $r)>{{ $r->label() }}</option> @endforeach
                        </select>
                    </td>
                    <td class="px-3 py-2">
                        <span @class(['rounded-full px-2 py-0.5 text-xs', 'bg-good-bg text-good-ink' => $u->is_active, 'bg-bad-bg text-bad-ink' => ! $u->is_active])>
                            {{ $u->is_active ? 'Active' : 'Deactivated' }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-right">
                        <button wire:click="toggleActive({{ $u->id }})" class="text-xs text-muted hover:text-ink">
                            {{ $u->is_active ? 'Deactivate' : 'Reactivate' }}
                        </button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
