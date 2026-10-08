@php
    $input = 'rounded-[9px] border border-line-2 bg-surface px-2.5 py-2 text-[13px]';
    $th = 'px-3.5 py-3 text-left text-[10.5px] font-bold uppercase tracking-[0.5px] text-muted';
@endphp
<div class="space-y-5">
    <x-page-heading title="Manage Users" subtitle="Add staff accounts, change roles, deactivate leavers" />
    @if ($notice) <p class="rounded-xl bg-warn-bg px-3 py-2 text-[13px] text-warn-ink">{{ $notice }}</p> @endif

    <x-card title="Add a user" icon="user">
        <form wire:submit="create" class="grid gap-3 sm:grid-cols-5">
            <div><input wire:model="name" placeholder="Full name" aria-label="Full name" class="{{ $input }} w-full">@error('name')<p class="text-xs text-bad-ink">{{ $message }}</p>@enderror</div>
            <div><input wire:model="email" type="email" placeholder="Work email" aria-label="Work email" class="{{ $input }} w-full">@error('email')<p class="text-xs text-bad-ink">{{ $message }}</p>@enderror</div>
            <select wire:model="role" class="{{ $input }}" aria-label="Role for new user">
                @foreach ($roles as $r) <option value="{{ $r->value }}">{{ $r->label() }}</option> @endforeach
            </select>
            <div><input wire:model="password" type="text" placeholder="Temporary password" aria-label="Temporary password" class="{{ $input }} w-full">@error('password')<p class="text-xs text-bad-ink">{{ $message }}</p>@enderror</div>
            <button class="btn btn-primary">Add user</button>
        </form>
    </x-card>

    <x-data-table min-width="640px">
        <thead class="bg-subtle">
            <tr><th class="{{ $th }}">Name</th><th class="{{ $th }}">Email</th><th class="{{ $th }}">Role</th><th class="{{ $th }}">Status</th><th class="{{ $th }}"><span class="sr-only">Actions</span></th></tr>
        </thead>
        <tbody>
        @foreach ($users as $u)
            <tr wire:key="user-{{ $u->id }}" class="border-t border-line hover:bg-subtle">
                <td class="px-3.5 py-3"><div class="flex items-center gap-2"><x-avatar :user="$u" /> <span class="font-semibold">{{ $u->name }}</span></div></td>
                <td class="px-3.5 py-3">{{ $u->email }}</td>
                <td class="px-3.5 py-3">
                    <select wire:change="changeRole({{ $u->id }}, $event.target.value)" class="{{ $input }} py-1" aria-label="Role for {{ $u->name }}">
                        @foreach ($roles as $r) <option value="{{ $r->value }}" @selected($u->role === $r)>{{ $r->label() }}</option> @endforeach
                    </select>
                </td>
                <td class="px-3.5 py-3">
                    <span @class(['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold', 'bg-good-bg text-good-ink' => $u->is_active, 'bg-bad-bg text-bad-ink' => ! $u->is_active])>
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $u->is_active ? 'Active' : 'Deactivated' }}
                    </span>
                </td>
                <td class="px-3.5 py-3 text-right">
                    <button wire:click="toggleActive({{ $u->id }})" class="btn btn-outline !px-3 !py-1.5">
                        {{ $u->is_active ? 'Deactivate' : 'Reactivate' }}
                    </button>
                </td>
            </tr>
        @endforeach
        </tbody>
    </x-data-table>
</div>
