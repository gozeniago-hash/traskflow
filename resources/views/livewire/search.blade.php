<div>
    <x-page-header title="Search" :subtitle="$q !== '' ? 'Results for “'.$q.'”' : 'Search tasks, projects and docs — or type a ticket number like BWR-12'" />
    <div class="max-w-4xl px-6 py-5">
        <input wire:model.live.debounce.300ms="q" placeholder="Search…" autofocus class="input mb-6">

        @if ($ticket)
            <button wire:click="$dispatch('open-task', { id: {{ $ticket->id }} })" data-peek-title="{{ $ticket->title }}" class="mb-6 flex w-full items-center gap-3 rounded-lg border border-coral-200 bg-coral-50 px-4 py-3 text-left hover:border-coral-400">
                <span class="rounded bg-white px-2 py-1 font-mono text-sm font-semibold text-coral-700 ring-1 ring-coral-200">{{ $ticket->ticket() }}</span>
                <span class="min-w-0 flex-1"><span class="block truncate font-medium">{{ $ticket->title }}</span><span class="text-xs text-ink-500">{{ $ticket->project->name }}{{ $ticket->assignee ? ' · '.$ticket->assignee->name : '' }}{{ $ticket->isCompleted() ? ' · Completed' : '' }}</span></span>
                <span class="text-sm font-medium text-coral-700">Open →</span>
            </button>
        @endif

        @if ($q !== '')
            <h2 class="mb-2 text-sm font-semibold">Projects <span class="font-normal text-ink-500">{{ $projects->count() }}</span></h2>
            <ul class="mb-8 divide-y divide-gray-100 border-y border-gray-100">
                @forelse ($projects as $p)
                    <li><a href="{{ route('projects.show', $p) }}" wire:navigate class="flex items-center gap-2 px-2 py-2 text-sm hover:bg-gray-50">
                        <span class="h-2.5 w-2.5 rounded-[3px]" style="background-color: {{ $p->color }}"></span><span class="font-mono text-[11px] text-ink-500">{{ $p->key }}</span> {{ $p->name }}
                        @if ($p->archived_at)<span class="pill bg-gray-100 text-ink-500">Archived</span>@endif
                        <x-status-pill :status="$p->status" class="ml-auto" />
                    </a></li>
                @empty
                    <li class="px-2 py-2 text-sm text-ink-500">No matching projects.</li>
                @endforelse
            </ul>

            @if ($docs->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold">Docs <span class="font-normal text-ink-500">{{ $docs->count() }}</span></h2>
                <ul class="mb-8 divide-y divide-gray-100 border-y border-gray-100">
                    @foreach ($docs as $d)
                        <li wire:key="sd-{{ $d->id }}"><a href="{{ route('projects.show', [$d->project_id, 'docs']) }}?doc={{ $d->id }}" wire:navigate class="block px-2 py-2 hover:bg-gray-50">
                            <span class="flex items-center gap-2 text-sm font-medium"><x-icon name="list" class="h-4 w-4 text-ink-300" />{{ $d->title }}
                                <span class="ml-auto max-w-40 truncate rounded px-1.5 py-0.5 text-[11px] font-medium text-white" style="background-color: {{ \App\Support\Color::forWhiteText($d->project->color) }}">{{ $d->project->name }}</span></span>
                            <span class="mt-0.5 block truncate pl-6 text-xs text-ink-500">{{ $d->excerpt(160) }}</span>
                        </a></li>
                    @endforeach
                </ul>
            @endif

            <h2 class="mb-2 text-sm font-semibold">Tasks <span class="font-normal text-ink-500">{{ $tasks->count() }}</span></h2>
            <ul class="divide-y divide-gray-100 border-y border-gray-100">
                @forelse ($tasks as $t)
                    <li wire:key="s-{{ $t->id }}"><button wire:click="$dispatch('open-task', { id: {{ $t->id }} })" data-peek-title="{{ $t->title }}" class="flex w-full items-center gap-2.5 px-2 py-2 text-left text-sm hover:bg-gray-50">
                        <span class="check {{ $t->isCompleted() ? 'check-done' : '' }}"><x-icon name="check" class="h-3 w-3" /></span>
                        <span class="flex-1 truncate {{ $t->isCompleted() ? 'text-ink-500 line-through' : '' }}"><span class="mr-1.5 font-mono text-[11px] text-ink-500">{{ $t->ticket() }}</span>{{ $t->title }}</span>
                        <span class="hidden max-w-40 truncate rounded px-1.5 py-0.5 text-[11px] font-medium text-white sm:inline" style="background-color: {{ \App\Support\Color::forWhiteText($t->project->color) }}">{{ $t->project->name }}</span>
                        <x-avatar :user="$t->assignee" size="xs" />
                    </button></li>
                @empty
                    <li class="px-2 py-2 text-sm text-ink-500">No matching tasks.</li>
                @endforelse
            </ul>
        @endif
    </div>
</div>
