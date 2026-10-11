{{-- Pop-up notifications. Pages raise them with a browser event: $dispatch('toast', { message, type }) — Livewire components
     use the Toasts trait; after a redirect the message arrives in the session flash 'toast'. --}}
<div x-data="{
        items: [], next: 1,
        push(t) {
            if (! t || ! t.message) return;
            const id = this.next++;
            this.items.push({ id, message: t.message, type: t.type || 'success' });
            setTimeout(() => this.drop(id), t.type === 'error' ? 8000 : 4000);
        },
        drop(id) { this.items = this.items.filter(i => i.id !== id) },
     }"
     x-init="push(@js(session('toast')))"
     @toast.window="push($event.detail)"
     class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4" data-toaster aria-live="polite">
    <template x-for="item in items" :key="item.id">
        <div x-transition role="status" data-toast :data-toast-type="item.type"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-xl border px-4 py-3 text-[13px] font-semibold shadow-lg"
             :class="{
                'border-good-ink/20 bg-good-bg text-good-ink': item.type === 'success',
                'border-bad-ink/20 bg-bad-bg text-bad-ink': item.type === 'error',
                'border-info-ink/20 bg-info-bg text-info-ink': item.type === 'info',
             }">
            <span class="min-w-0 flex-1" x-text="item.message"></span>
            <button type="button" @click="drop(item.id)" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Dismiss">✕</button>
        </div>
    </template>
</div>
