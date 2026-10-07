@php
    $desktop ??= false;
    $item = fn (string $href, string $label, string $icon, bool $active, ?int $count = null) => compact('href', 'label', 'icon', 'active', 'count');
    $list = fn (string $slug, string $label, string $icon, ?int $count) => $item(route('tenders.index', $slug), $label, $icon,
        request()->routeIs('tenders.index') && request()->route('list') === $slug, $count);
    $groups = [
        'Operations' => [
            $item(route('dashboard'), 'Dashboard', 'dashboard', request()->routeIs('dashboard')),
            $item(route('find-tenders.index'), 'Find Tenders', 'search', request()->routeIs('find-tenders.*')),
            $list('in-progress', 'In Progress', 'clock', $counts['in_progress']),
        ],
        'Pipeline' => [
            $list('done', 'Done', 'check', $counts['done']),
            $list('awarded', 'Awarded', 'award', $counts['awarded']),
            $list('lost', 'Lost', 'x', $counts['lost']),
        ],
        'Quotation' => [$item(route('quotations.index'), 'Quotations', 'quotation', request()->routeIs('quotations.*'))],
        'Insights' => [$item(route('status'), 'Status', 'staff', request()->routeIs('status'))],
        'Account' => array_values(array_filter([
            $item(route('settings'), 'Settings', 'settings', request()->routeIs('settings')),
            auth()->user()->can('manage-users') ? $item(route('users.index'), 'Manage Users', 'user', request()->routeIs('users.*')) : null,
            auth()->user()->can('manage-finance') ? $item(route('finance.settings'), 'Finance Settings', 'settings', request()->routeIs('finance.*')) : null,
        ])),
    ];
@endphp
<div class="flex h-full flex-col gap-0.5 p-3.5">
    <div class="flex items-center gap-2 px-1.5 pb-4 pt-2 folded:justify-center">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5" title="TenderHub"
           @if ($desktop) @click="if (folded) { $event.preventDefault(); toggleSidebar() }" @endif>
            <span class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-[9px] bg-chip text-sm font-extrabold text-accent">T</span>
            <span class="whitespace-nowrap text-[17px] font-extrabold tracking-tight folded:hidden">TenderHub</span>
        </a>
        @if ($desktop)
            <button type="button" @click="toggleSidebar()" title="Fold the menu" aria-label="Fold the menu"
                    class="ml-auto grid h-[30px] w-[30px] place-items-center rounded-[9px] text-muted hover:bg-hover hover:text-ink folded:hidden">
                <x-icon name="panel" class="h-[17px] w-[17px]" />
            </button>
        @endif
    </div>

    <nav class="flex-1 overflow-y-auto">
        @foreach ($groups as $heading => $items)
            <div class="mb-3">
                <p class="px-2.5 py-1.5 text-[10.5px] font-bold uppercase tracking-[0.6px] text-muted folded:h-2 folded:overflow-hidden folded:p-0 folded:text-transparent">{{ $heading }}</p>
                @foreach ($items as $i)
                    <a href="{{ $i['href'] }}" title="{{ $i['label'] }}" data-nav="{{ $i['label'] }}" data-icon="{{ $i['icon'] }}" @class([
                        'mb-px flex items-center gap-2.5 rounded-[11px] px-2.5 py-2 text-[13.5px] folded:justify-center folded:px-2',
                        'bg-accent font-bold text-accent-ink' => $i['active'],
                        'font-semibold text-muted hover:bg-line hover:text-ink' => ! $i['active'],
                    ])>
                        <x-icon :name="$i['icon']" class="h-[17px] w-[17px]" />
                        <span class="min-w-0 flex-1 truncate folded:hidden">{{ $i['label'] }}</span>
                        @if (! is_null($i['count']))
                            <span @class(['rounded-md px-1.5 text-[10.5px] font-bold folded:hidden',
                                'bg-accent-ink/15 text-accent-ink' => $i['active'], 'bg-hover text-muted' => ! $i['active']])>{{ $i['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="mt-auto flex items-center gap-2.5 border-t border-line px-2.5 pt-3 folded:justify-center folded:px-0">
        <x-avatar :user="auth()->user()" class="h-8 w-8 text-[13px]" />
        <div class="min-w-0 flex-1 folded:hidden">
            <p class="truncate text-[12.5px] font-semibold">{{ auth()->user()->name }}</p>
            <p class="text-[10.5px] text-muted">{{ auth()->user()->role->label() }}</p>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="folded:hidden">
            @csrf
            <button type="submit" title="Log out" aria-label="Log out" class="grid h-7 w-7 place-items-center rounded-lg text-muted-2 hover:bg-bad-bg hover:text-bad-ink">
                <x-icon name="logout" class="h-4 w-4" />
            </button>
        </form>
    </div>
</div>
