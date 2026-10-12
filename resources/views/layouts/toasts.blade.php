{{-- Toasts: session flashes + Livewire `$this->dispatch('toast', message: '...')` --}}
<div x-data="{ toasts: [], add(m, type = 'success') { const id = Date.now() + Math.random(); this.toasts.push({ id, m, type }); setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 3500) } }"
     x-init="@if (session('status')) add(@js(session('status'))) @endif"
     x-on:toast.window="add($event.detail.message, $event.detail.type || 'success')"
     class="pointer-events-none fixed bottom-4 left-1/2 z-[60] flex -translate-x-1/2 flex-col items-center gap-2">
    <template x-for="t in toasts" :key="t.id">
        <div class="pointer-events-auto flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm text-white shadow-lg"
             :class="t.type === 'error' ? 'bg-red-600' : 'bg-ink-900'">
            <svg class="h-4 w-4 text-emerald-400" x-show="t.type !== 'error'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            <span x-text="t.m"></span>
        </div>
    </template>
</div>
