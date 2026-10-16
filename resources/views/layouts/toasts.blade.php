{{-- Toasts: session flashes + Livewire `$this->toast('…', undo: $token)` (see InteractsWithWorkspace) --}}
<div x-data="tfToasts(@js(session('status')))"
     x-on:toast.window="add($event.detail)"
     role="status" aria-live="polite"
     class="pointer-events-none fixed bottom-4 left-1/2 z-[60] flex w-[calc(100%-2rem)] max-w-md -translate-x-1/2 flex-col items-center gap-2">
    <template x-for="t in toasts" :key="t.id">
        <div class="pointer-events-auto flex items-center gap-3 rounded-lg px-4 py-2.5 text-sm text-white shadow-lg"
             :class="t.type === 'error' ? 'bg-red-600' : 'bg-ink-900'">
            <svg class="h-4 w-4 shrink-0 text-emerald-400" x-show="t.type !== 'error'" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
            <span x-text="t.m"></span>
            <button type="button" x-show="t.undo" x-on:click="undo(t)" :disabled="t.busy"
                    class="-my-1 -mr-2 ml-1 shrink-0 rounded-md px-2.5 py-1.5 font-semibold text-accent-300 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">Undo</button>
        </div>
    </template>
</div>

{{-- In-app confirm / prompt dialog. Every wire:confirm uses it, and so does window.tfDialog in scripts. --}}
<div x-data="tfDialogHost" x-on:tf-dialog.window="open($event.detail)" x-on:keydown.escape.window="if (visible) close(null)">
    <template x-if="visible">
        <div class="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-ink-950/40 transition-opacity duration-150" :class="shown ? 'opacity-100' : 'opacity-0'" x-on:click="close(null)" aria-hidden="true"></div>
            <form x-on:submit.prevent="close(prompt ? value : true)" role="alertdialog" aria-modal="true" :aria-labelledby="'tf-dlg-title'" :aria-describedby="detail ? 'tf-dlg-text' : null"
                  class="relative w-full max-w-sm rounded-xl bg-white p-5 shadow-xl ring-1 ring-black/5 transition duration-150"
                  :class="shown ? 'translate-y-0 scale-100 opacity-100' : 'translate-y-2 scale-95 opacity-0'">
                <h2 id="tf-dlg-title" class="font-semibold text-ink-900" x-text="title"></h2>
                <p id="tf-dlg-text" x-show="detail" class="mt-1.5 text-sm text-ink-500" x-text="detail"></p>
                <template x-if="prompt">
                    <input x-ref="input" x-model="value" class="input mt-3" :aria-label="title" maxlength="255">
                </template>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" x-on:click="close(null)" class="btn-secondary btn" x-text="cancelLabel"></button>
                    <button type="submit" x-ref="ok" class="btn" :class="danger ? 'bg-red-600 text-white hover:bg-red-700' : 'btn-primary'" x-text="okLabel"></button>
                </div>
            </form>
        </div>
    </template>
</div>
