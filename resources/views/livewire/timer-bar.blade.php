<div>
    @if ($entry && $entry->task)
        @php($elapsed = $entry->elapsedSeconds())
        <div wire:key="tb-{{ $entry->id }}" x-data="tfTimer(true, {{ $elapsed }})"
             class="flex items-center gap-2 rounded-full bg-coral-50 py-1 pl-1 pr-3 ring-1 ring-coral-200">
            <button wire:click="stop" title="Stop timer" class="flex h-6 w-6 items-center justify-center rounded-full bg-coral-500 text-white hover:bg-coral-600">
                <x-icon name="stop" class="h-3 w-3" />
            </button>
            <span class="text-sm font-semibold tabular-nums text-coral-700" x-text="clock">{{ \App\Models\TimeEntry::formatClock($elapsed) }}</span>
            <button wire:click="$dispatch('open-task', { id: {{ $entry->task_id }} })" class="hidden max-w-44 truncate text-sm text-coral-800 hover:underline md:block">{{ $entry->task->title }}</button>
        </div>
    @endif
</div>
