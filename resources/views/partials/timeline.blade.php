{{-- History as a timeline (tender Activity, quotation History). $entries: ActivityLog with ->user loaded. --}}
<ol class="relative ml-1.5 space-y-4 border-l-2 border-line pl-5">
    @forelse ($entries as $entry)
        <li class="relative">
            <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full border-2 border-surface bg-accent-solid"></span>
            <p class="text-[13.5px]">{{ $entry->description }}</p>
            <p class="text-xs text-muted">
                by {{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, g:i a') }}
            </p>
        </li>
    @empty
        <li class="text-[13.5px] text-muted">No activity yet.</li>
    @endforelse
</ol>
