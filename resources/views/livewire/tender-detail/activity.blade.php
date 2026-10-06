<section class="rounded-xl border border-line bg-surface p-4">
    <ol class="space-y-3 text-sm">
        @forelse ($activity as $entry)
            <li class="flex gap-3">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                <div>
                    <p>{{ $entry->description }}</p>
                    <p class="text-xs text-muted">
                        {{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->setTimezone('Asia/Kuala_Lumpur')->format('d M Y, g:i a') }}
                    </p>
                </div>
            </li>
        @empty
            <li class="text-muted">No activity yet.</li>
        @endforelse
    </ol>
</section>
