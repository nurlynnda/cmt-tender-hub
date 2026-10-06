@php
    $item = fn (string $route, array $params, string $label, ?int $count = null) => [
        'href' => route($route, $params), 'label' => $label, 'count' => $count,
        'active' => url()->current() === route($route, $params),
    ];
    $groups = [
        'Operations' => [$item('tenders.index', ['in-progress'], 'In Progress', $counts['in_progress'])],
        'Pipeline' => [
            $item('tenders.index', ['done'], 'Done', $counts['done']),
            $item('tenders.index', ['awarded'], 'Awarded', $counts['awarded']),
            $item('tenders.index', ['lost'], 'Lost', $counts['lost']),
        ],
    ];
@endphp
<div class="flex h-full flex-col p-4">
    <a href="{{ route('tenders.index', 'in-progress') }}" class="mb-6 flex items-center gap-2">
        <span class="grid h-8 w-8 place-items-center rounded-lg bg-accent font-bold text-accent-ink">T</span>
        <span class="font-semibold">TenderHub</span>
    </a>

    <nav class="flex-1 space-y-5">
        @foreach ($groups as $heading => $items)
            <div>
                <p class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-muted">{{ $heading }}</p>
                @foreach ($items as $i)
                    <a href="{{ $i['href'] }}" @class([
                        'flex items-center justify-between rounded-lg px-2 py-1.5 text-sm',
                        'bg-accent-tint font-medium' => $i['active'],
                        'hover:bg-hover' => ! $i['active'],
                    ])>
                        <span>{{ $i['label'] }}</span>
                        <span class="rounded-full bg-subtle px-2 text-xs text-muted">{{ $i['count'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach

        <div>
            <p class="mb-1 px-2 text-xs font-medium uppercase tracking-wide text-muted">Account</p>
            <a href="{{ route('settings') }}" class="block rounded-lg px-2 py-1.5 text-sm hover:bg-hover">Settings</a>
            @can('manage-users')
                <a href="{{ route('users.index') }}" class="block rounded-lg px-2 py-1.5 text-sm hover:bg-hover">Manage Users</a>
            @endcan
        </div>
    </nav>

    <div class="mt-4 flex items-center gap-2 border-t border-line pt-4">
        <x-avatar :user="auth()->user()" />
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
            <p class="text-xs text-muted">{{ auth()->user()->role->label() }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg p-2 text-muted hover:bg-hover hover:text-ink" title="Log out" aria-label="Log out">
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </form>
    </div>
</div>
