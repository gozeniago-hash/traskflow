{{--
    Start/stop timer for one task. Use inside a Livewire view whose component has
    startTimer(int $taskId) and stopTimer() actions.
    $task->tracked_seconds = closed time (withSum); $running = the viewer's running TimeEntry or null.
--}}
@props(['task', 'running' => null])
@php
    $isRunning = $running && (int) $running->task_id === (int) $task->id;
    $base = (int) ($task->tracked_seconds ?? 0) + ($isRunning ? $running->elapsedSeconds() : 0);
@endphp
<div wire:key="timer-{{ $task->id }}-{{ $isRunning ? 'on' : 'off' }}-{{ $base }}"
     x-data="tfTimer({{ $isRunning ? 'true' : 'false' }}, {{ $base }})"
     {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
    @if ($isRunning)
        <button type="button" wire:click.stop="stopTimer" wire:loading.attr="disabled" wire:target="startTimer,stopTimer" title="Stop timer"
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-accent-600 text-white shadow-sm ring-4 ring-accent-100 hover:bg-accent-600">
            <x-icon name="stop" class="h-2.5 w-2.5" />
        </button>
        <span class="text-xs font-semibold tabular-nums text-accent-600" x-text="clock">{{ \App\Models\TimeEntry::formatClock($base) }}</span>
    @else
        <button type="button" wire:click.stop="startTimer({{ $task->id }})" wire:loading.attr="disabled" wire:target="startTimer,stopTimer" title="Start timer"
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-gray-300 bg-white text-ink-500 hover:border-accent-400 hover:text-accent-600">
            <x-icon name="play" class="ml-px h-2.5 w-2.5" />
        </button>
        @if ($base > 0)
            <span class="text-xs tabular-nums text-ink-500">{{ \App\Models\TimeEntry::formatShort($base) }}</span>
        @endif
    @endif
</div>
