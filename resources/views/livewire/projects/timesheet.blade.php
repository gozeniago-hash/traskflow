@php($fmt = fn (int $s) => \App\Models\TimeEntry::formatShort($s))
<div class="space-y-5 p-6">
    {{-- Filters + export --}}
    <div class="flex flex-wrap items-center gap-2">
        <select wire:model.live="range" class="input input-sm w-auto text-xs" aria-label="Date range">
            @foreach (\App\Livewire\Projects\Timesheet::rangeLabels() as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
        </select>
        <input type="date" wire:model.live="from" class="input input-sm w-36 text-xs" aria-label="From">
        <span class="text-xs text-ink-500">to</span>
        <input type="date" wire:model.live="to" class="input input-sm w-36 text-xs" aria-label="To">
        @if ($canSeeTeam)
        <select wire:model.live="person" class="input input-sm w-auto text-xs" aria-label="Person">
            <option value="">Everyone</option>
            @foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
        </select>
        @else
            <span class="pill bg-gray-100 text-ink-700">Your time only</span>
        @endif
        <span wire:loading.delay class="text-xs text-ink-500">Updating…</span>
        <div class="ml-auto flex gap-2">
            <button wire:click="export('xlsx')" class="btn-secondary btn text-xs" @disabled(! $count)>
                <x-icon name="download" /> <span wire:loading.remove wire:target="export('xlsx')">Excel</span><span wire:loading wire:target="export('xlsx')">Preparing…</span>
            </button>
            <button wire:click="export('pdf')" class="btn-secondary btn text-xs" @disabled(! $count)>
                <x-icon name="download" /> <span wire:loading.remove wire:target="export('pdf')">PDF</span><span wire:loading wire:target="export('pdf')">Preparing…</span>
            </button>
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs text-ink-500">Total time · {{ $sheet->rangeLabel() }}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fmt($total) }}</p>
            <p class="text-[11px] text-ink-500">{{ \App\Support\Timesheet::hours($total) }} hours</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-ink-500">Entries</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $count }}</p>
            <p class="text-[11px] text-ink-500">across {{ $days->count() }} {{ \Illuminate\Support\Str::plural('day', $days->count()) }}</p>
        </div>
        <div class="card p-4 xl:col-span-2">
            <p class="mb-2 text-xs text-ink-500">By person</p>
            @forelse ($people->sortByDesc('seconds')->take(5) as $row)
                <div class="flex items-center gap-2 py-0.5 text-sm">
                    <x-avatar :user="$row['user']" size="xs" />
                    <span class="w-28 truncate">{{ $row['user']?->name ?? 'Former member' }}</span>
                    <x-progress-bar :percent="$total ? (int) round($row['seconds'] / $total * 100) : 0" color="#f06a6a" class="flex-1" />
                    <span class="w-16 text-right text-xs tabular-nums text-ink-700">{{ $fmt($row['seconds']) }}</span>
                </div>
            @empty
                <p class="text-sm text-ink-500">—</p>
            @endforelse
        </div>
    </div>

    @if ($running->isNotEmpty())
        <p class="flex items-center gap-2 rounded-md bg-accent-50 px-3 py-2 text-xs text-accent-800">
            <span class="h-2 w-2 animate-pulse rounded-full bg-accent-500"></span>
            Timer running for {{ $running->pluck('user.name')->filter()->join(', ', ' and ') }}. It appears here once stopped.
        </p>
    @endif

    {{-- Entries by day --}}
    @if ($days->isEmpty())
        <div class="card px-6 py-12 text-center">
            <x-icon name="clock" class="mx-auto h-8 w-8 text-ink-300" />
            <p class="mt-2 font-medium">No time recorded {{ $person ? 'for this person ' : '' }}in this period</p>
            <p class="mt-1 text-sm text-ink-500">Start the timer on any task, or add time manually from the task panel.</p>
        </div>
    @else
        <div class="card overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-ink-500">
                        <th class="px-4 py-2 font-medium">Person</th>
                        <th class="px-4 py-2 font-medium">Task</th>
                        <th class="px-4 py-2 font-medium">Note</th>
                        <th class="px-4 py-2 font-medium">Time</th>
                        <th class="px-4 py-2 text-right font-medium">Duration</th>
                    </tr>
                </thead>
                @foreach ($days as $d)
                    <tbody wire:key="day-{{ $d['day']->toDateString() }}">
                        <tr class="bg-gray-50">
                            <td colspan="4" class="px-4 py-1.5 text-xs font-semibold text-ink-700">{{ $d['day']->isToday() ? 'Today' : ($d['day']->isYesterday() ? 'Yesterday' : $d['day']->format('l, M j')) }}{{ $d['day']->year !== now()->year ? ', '.$d['day']->year : '' }}</td>
                            <td class="px-4 py-1.5 text-right text-xs font-semibold tabular-nums text-ink-700">{{ $fmt($d['seconds']) }}</td>
                        </tr>
                        @foreach ($d['entries'] as $e)
                            <tr class="border-t border-gray-100 hover:bg-gray-50/60">
                                <td class="px-4 py-2"><span class="flex items-center gap-2"><x-avatar :user="$e->user" size="xs" /><span class="truncate">{{ $e->user?->name ?? 'Former member' }}</span></span></td>
                                <td class="max-w-72 px-4 py-2">
                                    @if ($e->task)
                                        <button type="button" wire:click="$dispatch('open-task', { id: {{ $e->task_id }} })" data-peek-title="{{ $e->task->title }}" class="flex max-w-full items-center gap-1.5 text-left hover:underline">
                                            <span class="shrink-0 font-mono text-[11px] text-ink-500">{{ $project->key }}-{{ $e->task->number }}</span>
                                            <span class="truncate">{{ $e->task->title }}</span>
                                        </button>
                                    @else
                                        <span class="text-ink-500">Task deleted</span>
                                    @endif
                                </td>
                                <td class="max-w-72 px-4 py-2 text-ink-700"><span class="line-clamp-2">{{ $e->note ?: '—' }}</span></td>
                                <td class="whitespace-nowrap px-4 py-2 text-xs tabular-nums text-ink-500">
                                    @if ($e->is_manual)<span class="pill bg-gray-100 text-ink-500">Added manually</span>@else{{ $e->local_start->format('g:i A') }} – {{ $e->ended_at->copy()->setTimezone(\App\Support\Timesheet::tz())->format('g:i A') }}@endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">{{ $fmt((int) $e->duration_seconds) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
                <tfoot>
                    <tr class="border-t-2 border-gray-200">
                        <td colspan="4" class="px-4 py-2.5 text-sm font-semibold">Total</td>
                        <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ $fmt($total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-xs text-ink-500">Times shown in {{ \App\Support\Timesheet::tz() }}.</p>
    @endif
</div>
