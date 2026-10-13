@php
    $isOpen = fn ($key) => $open[$key] ?? ($open['__all'] ?? false);
    $rangeLabels = [
        'today' => 'Today', 'this_week' => 'This week', 'last_week' => 'Last week', 'last_7' => 'Last 7 days',
        'this_month' => 'This month', 'last_month' => 'Last month', 'last_30' => 'Last 30 days',
        'this_quarter' => 'This quarter', 'this_year' => 'This year', 'custom' => 'Custom range',
    ];
@endphp
<div>
    <x-page-header title="Reports" :subtitle="$scopeNote">
        <x-slot:actions>
            <button onclick="window.print()" class="btn-secondary btn print:hidden">Print</button>
            @if ($report === 'timesheet')
                <button wire:click="exportTimesheet('xlsx')" class="btn-primary btn print:hidden" @disabled(! $data['count'])><x-icon name="download" /> Excel</button>
                <button wire:click="exportTimesheet('pdf')" class="btn-primary btn print:hidden" @disabled(! $data['count'])><x-icon name="download" /> PDF</button>
            @else
                <button wire:click="export" class="btn-primary btn print:hidden"><x-icon name="download" /> Export CSV</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Report tabs --}}
    <nav class="flex gap-5 overflow-x-auto border-b border-gray-200 px-6 pt-3 print:hidden">
        @foreach (\App\Livewire\Reports\Index::REPORTS as $key => $label)
            <button wire:click="$set('report', '{{ $key }}')" class="tab whitespace-nowrap {{ $report === $key ? 'tab-active' : '' }}">{{ $label }}</button>
        @endforeach
    </nav>

    {{-- Filters: one row --}}
    <div class="flex flex-wrap items-center gap-2 px-6 py-3 print:hidden">
        <select wire:model.live="project" class="input input-sm w-auto text-xs" aria-label="Project">
            <option value="">All projects</option>
            @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
        </select>
        <select wire:model.live="team" class="input input-sm w-auto text-xs" aria-label="Team">
            <option value="">All teams</option>
            @foreach ($teams as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
        </select>
        @if ($report !== 'projects')
            <select wire:model.live="person" class="input input-sm w-auto text-xs" aria-label="Resource">
                <option value="">Everyone</option>
                @foreach ($people as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                @if ($report !== 'timesheet')<option value="unassigned">Unassigned</option>@endif
            </select>
        @endif

        @if ($report === 'upcoming')
            <select wire:model.live="days" class="input input-sm w-auto text-xs" aria-label="Window">
                @foreach ([7, 14, 30, 60, 90] as $d)<option value="{{ $d }}">Next {{ $d }} days</option>@endforeach
            </select>
        @endif

        @if (in_array($report, ['completed', 'timesheet'], true))
            <select wire:model.live="range" class="input input-sm w-auto text-xs" aria-label="Date range">
                @foreach ($rangeLabels as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
            </select>
            <input type="date" wire:model.live="from" x-on:change="$wire.set('range', 'custom')" class="input input-sm w-36 text-xs" aria-label="From">
            <span class="text-xs text-ink-500">to</span>
            <input type="date" wire:model.live="to" x-on:change="$wire.set('range', 'custom')" class="input input-sm w-36 text-xs" aria-label="To">
        @endif

        @if ($report === 'timesheet')
        @elseif ($report === 'projects')
            <label class="flex items-center gap-1.5 text-xs text-ink-700"><input type="checkbox" wire:model.live="includeArchived" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Include archived</label>
        @else
            <label class="flex items-center gap-1.5 text-xs text-ink-700"><input type="checkbox" wire:model.live="includeSubtasks" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Include subtasks</label>
        @endif

        <button wire:click="resetFilters" class="text-xs text-ink-500 hover:text-ink-900 hover:underline">Reset</button>
        <span wire:loading class="text-xs text-ink-500">Updating…</span>
    </div>

    <div class="px-6 pb-12">
        <h2 class="mb-4 hidden text-lg font-semibold print:block">{{ \App\Livewire\Reports\Index::REPORTS[$report] }} — {{ now()->format('M j, Y') }}</h2>

        {{-- ============================================== INCOMPLETE BY RESOURCE --}}
        @if ($report === 'incomplete')
            @php($t = $data['totals'])
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                @foreach ([['Open tasks', $t['total'], 'text-ink-900'], ['Overdue', $t['overdue'], $t['overdue'] ? 'text-red-600' : 'text-ink-900'], ['Due in 7 days', $t['this_week'], 'text-ink-900'], ['Due later', $t['later'], 'text-ink-900'], ['No due date', $t['no_date'], 'text-ink-900']] as [$label, $value, $color])
                    <div class="card p-4"><p class="text-xs text-ink-500">{{ $label }}</p><p class="mt-1 text-2xl font-semibold tabular-nums {{ $color }}">{{ $value }}</p></div>
                @endforeach
            </div>

            <div class="card mt-5">
                <div class="flex flex-wrap items-center gap-4 border-b border-gray-100 px-4 py-3 text-xs text-ink-700">
                    <span class="font-semibold text-ink-900">Open work per person</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-red-500"></span>Overdue</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-400"></span>Due in 7 days</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-slate-400"></span>Due later</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-slate-200"></span>No due date</span>
                    <span class="ml-auto flex gap-2 print:hidden">
                        <button wire:click="expandAll(true)" class="hover:underline">Expand all</button>
                        <button wire:click="expandAll(false)" class="hover:underline">Collapse all</button>
                    </span>
                </div>

                @forelse ($data['rows'] as $row)
                    <div wire:key="inc-{{ $row['key'] }}" class="border-b border-gray-100 last:border-0">
                        <button wire:click="toggle('{{ $row['key'] }}')" class="grid w-full grid-cols-[minmax(0,180px)_minmax(0,1fr)_auto] items-center gap-4 px-4 py-2.5 text-left hover:bg-gray-50">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-ink-500 transition {{ $isOpen($row['key']) ? 'rotate-90' : '' }}" />
                                <x-avatar :user="$row['user']" size="sm" />
                                <span class="truncate text-sm font-medium">{{ $row['user']?->name ?? 'Unassigned' }}</span>
                            </span>
                            <span class="flex h-3 items-center gap-0.5" style="width: {{ max(4, $row['total'] / $data['max'] * 100) }}%">
                                @foreach ([['overdue', 'bg-red-500', 'overdue'], ['this_week', 'bg-amber-400', 'due in 7 days'], ['later', 'bg-slate-400', 'due later'], ['no_date', 'bg-slate-200', 'no due date']] as [$k, $cls, $label])
                                    @if ($row[$k])<span title="{{ $row[$k] }} {{ $label }}" class="h-full rounded-sm {{ $cls }}" style="flex-grow: {{ $row[$k] }}"></span>@endif
                                @endforeach
                            </span>
                            <span class="flex items-center gap-3 text-xs tabular-nums text-ink-500">
                                @if ($row['overdue'])<span class="font-medium text-red-600">{{ $row['overdue'] }} overdue</span>@endif
                                <span class="w-14 text-right text-sm font-semibold text-ink-900">{{ $row['total'] }}</span>
                            </span>
                        </button>
                        @if ($isOpen($row['key']))
                            @include('livewire.reports.partials-task-list', ['tasks' => $row['tasks'], 'mode' => 'open'])
                        @endif
                    </div>
                @empty
                    <x-empty class="m-4" icon="check-circle" title="No open tasks" text="Nothing matches these filters." />
                @endforelse
            </div>
        @endif

        {{-- ============================================== UPCOMING BY RESOURCE --}}
        @if ($report === 'upcoming')
            <div class="card p-4">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-semibold">Tasks due per day <span class="font-normal text-ink-500">· {{ $data['start']->format('M j') }} – {{ $data['end']->format('M j') }}</span></p>
                    <p class="text-sm"><span class="text-2xl font-semibold tabular-nums">{{ $data['total'] }}</span> <span class="text-ink-500">tasks due</span></p>
                </div>
                <div class="flex h-28 items-end gap-0.5 border-b border-gray-200">
                    @foreach ($data['perDay'] as $date => $count)
                        @php($d = \Carbon\Carbon::parse($date))
                        <div class="group relative flex h-full flex-1 items-end" title="{{ $d->format('D, M j') }}: {{ $count }} {{ \Illuminate\Support\Str::plural('task', $count) }}">
                            <div class="w-full rounded-t-[3px] {{ $count ? 'bg-coral-500 group-hover:bg-coral-600' : '' }}" style="height: {{ $count ? max(6, $count / $data['maxDay'] * 100) : 0 }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex justify-between text-[11px] text-ink-500">
                    <span>{{ $data['start']->format('M j') }}</span>
                    <span>max {{ $data['maxDay'] }}/day</span>
                    <span>{{ $data['end']->format('M j') }}</span>
                </div>
            </div>

            <div class="card mt-5">
                <div class="flex items-center border-b border-gray-100 px-4 py-3 text-xs">
                    <span class="font-semibold text-ink-900">Due in the next {{ $days }} days, per person</span>
                    <span class="ml-auto flex gap-2 text-ink-700 print:hidden">
                        <button wire:click="expandAll(true)" class="hover:underline">Expand all</button>
                        <button wire:click="expandAll(false)" class="hover:underline">Collapse all</button>
                    </span>
                </div>
                @forelse ($data['rows'] as $row)
                    <div wire:key="up-{{ $row['key'] }}" class="border-b border-gray-100 last:border-0">
                        <button wire:click="toggle('{{ $row['key'] }}')" class="grid w-full grid-cols-[minmax(0,180px)_minmax(0,1fr)_auto] items-center gap-4 px-4 py-2.5 text-left hover:bg-gray-50">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-ink-500 transition {{ $isOpen($row['key']) ? 'rotate-90' : '' }}" />
                                <x-avatar :user="$row['user']" size="sm" />
                                <span class="truncate text-sm font-medium">{{ $row['user']?->name ?? 'Unassigned' }}</span>
                            </span>
                            <span class="h-3"><span class="block h-full rounded-sm bg-coral-500" style="width: {{ max(3, $row['total'] / $data['max'] * 100) }}%" title="{{ $row['total'] }} due"></span></span>
                            <span class="flex items-center gap-3 text-xs text-ink-500">
                                <span>next: {{ $row['next']?->isToday() ? 'Today' : ($row['next']?->isTomorrow() ? 'Tomorrow' : $row['next']?->format('M j')) }}</span>
                                <span class="w-10 text-right text-sm font-semibold tabular-nums text-ink-900">{{ $row['total'] }}</span>
                            </span>
                        </button>
                        @if ($isOpen($row['key']))
                            @include('livewire.reports.partials-task-list', ['tasks' => $row['tasks'], 'mode' => 'open'])
                        @endif
                    </div>
                @empty
                    <x-empty class="m-4" icon="calendar" title="Nothing due in this window" text="Try a longer window or different filters." />
                @endforelse
            </div>
        @endif

        {{-- ============================================== PROJECTS BY STATUS --}}
        @if ($report === 'projects')
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                @foreach ($data['groups'] as $g)
                    <div class="card p-4">
                        <p class="flex items-center gap-1.5 text-xs text-ink-500"><span class="h-2 w-2 rounded-full {{ \App\Models\Project::STATUS_DOTS[$g['status']] }}"></span>{{ $g['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $g['projects']->count() }}</p>
                    </div>
                @endforeach
            </div>

            @if ($data['total'])
                <div class="mt-3 flex h-2.5 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
                    @foreach ($data['groups'] as $g)
                        @if ($g['projects']->count())<span class="h-full {{ \App\Models\Project::STATUS_DOTS[$g['status']] }}" style="flex-grow: {{ $g['projects']->count() }}" title="{{ $g['label'] }}: {{ $g['projects']->count() }}"></span>@endif
                    @endforeach
                </div>
            @endif

            <div class="mt-6 space-y-6">
                @foreach ($data['groups'] as $g)
                    @continue($g['projects']->isEmpty())
                    <section wire:key="ps-{{ $g['status'] }}">
                        <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold"><x-status-pill :status="$g['status']" /> <span class="font-normal text-ink-500">{{ $g['projects']->count() }}</span></h3>
                        <div class="card overflow-x-auto">
                            <table class="w-full min-w-[720px] text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-ink-500">
                                    <tr><th class="px-4 py-2 font-semibold">Project</th><th class="px-4 py-2 font-semibold">Owner</th><th class="px-4 py-2 font-semibold">Client</th><th class="w-40 px-4 py-2 font-semibold">Progress</th><th class="px-4 py-2 text-right font-semibold">Overdue</th><th class="px-4 py-2 text-right font-semibold">Logged</th><th class="px-4 py-2 font-semibold">Due</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($g['projects'] as $p)
                                        @php($pct = $p->tasks_total ? (int) round($p->tasks_done / $p->tasks_total * 100) : 0)
                                        <tr wire:key="pr-{{ $p->id }}">
                                            <td class="px-4 py-2.5">
                                                <a href="{{ route('projects.show', [$p, 'overview']) }}" wire:navigate class="flex items-center gap-2 font-medium hover:underline">
                                                    <span class="h-2.5 w-2.5 shrink-0 rounded-[3px]" style="background-color: {{ $p->color }}"></span><span class="font-mono text-[11px] text-ink-500">{{ $p->key }}</span> {{ $p->name }}
                                                    @if ($p->archived_at)<span class="pill bg-gray-100 text-ink-500">Archived</span>@endif
                                                </a>
                                            </td>
                                            <td class="px-4 py-2.5"><span class="flex items-center gap-1.5"><x-avatar :user="$p->owner" size="xs" />{{ $p->owner?->firstName() ?? '—' }}</span></td>
                                            <td class="px-4 py-2.5 text-ink-700">{{ $p->client?->name ?? 'Internal' }}</td>
                                            <td class="px-4 py-2.5">
                                                <div class="flex items-center gap-2"><x-progress-bar :percent="$pct" :color="$p->color" class="flex-1" /><span class="w-16 text-right text-xs tabular-nums text-ink-500">{{ $p->tasks_done }}/{{ $p->tasks_total }}</span></div>
                                            </td>
                                            <td class="px-4 py-2.5 text-right tabular-nums {{ $p->tasks_overdue ? 'font-medium text-red-600' : 'text-ink-500' }}">{{ $p->tasks_overdue }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums text-ink-700">{{ \App\Models\TimeEntry::formatShort((int) $p->tracked_seconds) }}</td>
                                            <td class="px-4 py-2.5 {{ $p->due_date?->isPast() && $p->status !== 'complete' ? 'text-red-600' : 'text-ink-700' }}">{{ $p->due_date?->format('M j, Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
                @if (! $data['total'])
                    <x-empty icon="folder" title="No projects match" />
                @endif
            </div>
        @endif

        {{-- ============================================== COMPLETED IN RANGE --}}
        @if ($report === 'completed')
            <p class="mb-3 text-sm text-ink-500">{{ $data['from']->format('M j, Y') }} – {{ $data['to']->format('M j, Y') }}</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="card p-4"><p class="text-xs text-ink-500">Tasks completed</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $data['total'] }}</p></div>
                <div class="card p-4"><p class="text-xs text-ink-500">On time</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $data['onTimeRate'] === null ? '—' : $data['onTimeRate'].'%' }}</p><p class="text-[11px] text-ink-500">of tasks that had a due date</p></div>
                <div class="card p-4"><p class="text-xs text-ink-500">Finished late</p><p class="mt-1 text-2xl font-semibold tabular-nums {{ $data['late'] ? 'text-red-600' : '' }}">{{ $data['late'] }}</p></div>
                <div class="card p-4"><p class="text-xs text-ink-500">Time logged</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ \App\Models\TimeEntry::formatShort($data['timeLogged']) }}</p></div>
            </div>

            <div class="card mt-5">
                <div class="flex items-center border-b border-gray-100 px-4 py-3 text-xs">
                    <span class="font-semibold text-ink-900">Completed per person</span>
                    <span class="ml-auto flex gap-2 text-ink-700 print:hidden">
                        <button wire:click="expandAll(true)" class="hover:underline">Expand all</button>
                        <button wire:click="expandAll(false)" class="hover:underline">Collapse all</button>
                    </span>
                </div>
                @forelse ($data['rows'] as $row)
                    <div wire:key="cp-{{ $row['key'] }}" class="border-b border-gray-100 last:border-0">
                        <button wire:click="toggle('{{ $row['key'] }}')" class="grid w-full grid-cols-[minmax(0,180px)_minmax(0,1fr)_auto] items-center gap-4 px-4 py-2.5 text-left hover:bg-gray-50">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-ink-500 transition {{ $isOpen($row['key']) ? 'rotate-90' : '' }}" />
                                <x-avatar :user="$row['user']" size="sm" />
                                <span class="truncate text-sm font-medium">{{ $row['user']?->name ?? 'Unassigned' }}</span>
                            </span>
                            <span class="h-3"><span class="block h-full rounded-sm bg-emerald-500" style="width: {{ max(3, $row['total'] / $data['max'] * 100) }}%" title="{{ $row['total'] }} completed"></span></span>
                            <span class="flex items-center gap-3 text-xs text-ink-500">
                                @if ($row['late'])<span class="text-red-600">{{ $row['late'] }} late</span>@endif
                                <span class="w-10 text-right text-sm font-semibold tabular-nums text-ink-900">{{ $row['total'] }}</span>
                            </span>
                        </button>
                        @if ($isOpen($row['key']))
                            @include('livewire.reports.partials-task-list', ['tasks' => $row['tasks'], 'mode' => 'completed'])
                        @endif
                    </div>
                @empty
                    <x-empty class="m-4" icon="check-circle" title="No tasks completed in this period" text="Pick a wider date range." />
                @endforelse
            </div>
        @endif

        {{-- ============================================== TIMESHEET BY RESOURCE --}}
        @if ($report === 'timesheet')
            <p class="mb-3 text-sm text-ink-500">{{ $data['sheet']->rangeLabel() }} · times in {{ \App\Support\Timesheet::tz() }}</p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="card p-4"><p class="text-xs text-ink-500">Time logged</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ \App\Models\TimeEntry::formatShort($data['total']) }}</p><p class="text-[11px] text-ink-500">{{ \App\Support\Timesheet::hours($data['total']) }} hours</p></div>
                <div class="card p-4"><p class="text-xs text-ink-500">Entries</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $data['count'] }}</p></div>
                <div class="card p-4"><p class="text-xs text-ink-500">People</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $data['rows']->count() }}</p></div>
                <div class="card p-4"><p class="text-xs text-ink-500">Projects</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $data['projectCount'] }}</p></div>
            </div>

            <div class="card mt-5">
                <div class="flex items-center border-b border-gray-100 px-4 py-3 text-xs">
                    <span class="font-semibold text-ink-900">Time per person</span>
                    <span class="ml-auto flex gap-2 text-ink-700 print:hidden">
                        <button wire:click="expandAll(true)" class="hover:underline">Expand all</button>
                        <button wire:click="expandAll(false)" class="hover:underline">Collapse all</button>
                    </span>
                </div>
                @forelse ($data['rows'] as $row)
                    <div wire:key="ts-{{ $row['key'] }}" class="border-b border-gray-100 last:border-0">
                        <button wire:click="toggle('{{ $row['key'] }}')" class="grid w-full grid-cols-[minmax(0,200px)_minmax(0,1fr)_auto] items-center gap-4 px-4 py-2.5 text-left hover:bg-gray-50">
                            <span class="flex min-w-0 items-center gap-2">
                                <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-ink-500 transition {{ $isOpen($row['key']) ? 'rotate-90' : '' }}" />
                                <x-avatar :user="$row['user']" size="sm" />
                                <span class="truncate text-sm font-medium">{{ $row['user']?->name ?? 'Former member' }}</span>
                            </span>
                            <span class="h-3"><span class="block h-full rounded-sm bg-coral-400" style="width: {{ max(3, $row['seconds'] / $data['max'] * 100) }}%"></span></span>
                            <span class="flex items-center gap-3 text-xs text-ink-500">
                                <span class="hidden sm:inline">{{ $row['count'] }} {{ \Illuminate\Support\Str::plural('entry', $row['count']) }} · {{ $row['projects'] }} {{ \Illuminate\Support\Str::plural('project', $row['projects']) }}</span>
                                <span class="w-20 text-right text-sm font-semibold tabular-nums text-ink-900">{{ \App\Models\TimeEntry::formatShort($row['seconds']) }}</span>
                            </span>
                        </button>
                        @if ($isOpen($row['key']))
                            <div class="bg-gray-50/60 px-4 pb-4 pt-1">
                                <div class="mb-2 flex flex-wrap gap-1.5 pl-6">
                                    @foreach ($row['byProject'] as $bp)
                                        <span class="pill bg-white text-ink-700 ring-1 ring-gray-200"><span class="h-2 w-2 rounded-sm" style="background-color: {{ $bp['project']?->color }}"></span>{{ $bp['project']?->name }} · {{ \App\Models\TimeEntry::formatShort($bp['seconds']) }}</span>
                                    @endforeach
                                </div>
                                <div class="overflow-x-auto rounded-md border border-gray-200 bg-white">
                                    <table class="w-full min-w-[640px] text-sm">
                                        <thead><tr class="border-b border-gray-100 text-left text-xs text-ink-500">
                                            <th class="px-3 py-1.5 font-medium">Date</th><th class="px-3 py-1.5 font-medium">Task</th><th class="px-3 py-1.5 font-medium">Note</th><th class="px-3 py-1.5 text-right font-medium">Duration</th>
                                        </tr></thead>
                                        <tbody>
                                            @foreach ($row['entries'] as $e)
                                                <tr class="border-t border-gray-50">
                                                    <td class="whitespace-nowrap px-3 py-1.5 text-ink-700">{{ $e->local_start->format('D, M j') }}</td>
                                                    <td class="max-w-80 px-3 py-1.5">
                                                        @if ($e->task)
                                                            <button type="button" wire:click="$dispatch('open-task', { id: {{ $e->task_id }} })" data-peek-title="{{ $e->task->title }}" class="flex max-w-full items-center gap-1.5 text-left hover:underline">
                                                                <span class="h-2 w-2 shrink-0 rounded-sm" style="background-color: {{ $e->project?->color }}"></span>
                                                                <span class="shrink-0 font-mono text-[11px] text-ink-500">{{ $e->project?->key }}-{{ $e->task->number }}</span>
                                                                <span class="truncate">{{ $e->task->title }}</span>
                                                            </button>
                                                        @else
                                                            <span class="text-ink-500">Task deleted</span>
                                                        @endif
                                                    </td>
                                                    <td class="max-w-72 truncate px-3 py-1.5 text-ink-500">{{ $e->note ?: '—' }}</td>
                                                    <td class="whitespace-nowrap px-3 py-1.5 text-right tabular-nums">{{ \App\Models\TimeEntry::formatShort((int) $e->duration_seconds) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <x-empty class="m-4" icon="clock" title="No time recorded in this period" text="Pick a wider date range or clear the filters." />
                @endforelse
            </div>
        @endif
    </div>
</div>
