<div>
    <x-page-header title="Search" :subtitle="$q !== '' ? 'Results for “'.$q.'”' : 'Search across tasks and projects you can access'" />
    <div class="max-w-4xl px-6 py-5">
        <input wire:model.live.debounce.300ms="q" placeholder="Search…" autofocus class="input mb-6">

        @if ($q !== '')
            <h2 class="mb-2 text-sm font-semibold">Projects <span class="font-normal text-ink-500">{{ $projects->count() }}</span></h2>
            <ul class="mb-8 divide-y divide-gray-100 border-y border-gray-100">
                @forelse ($projects as $p)
                    <li><a href="{{ route('projects.show', $p) }}" wire:navigate class="flex items-center gap-2 px-2 py-2 text-sm hover:bg-gray-50">
                        <span class="h-2.5 w-2.5 rounded-[3px]" style="background-color: {{ $p->color }}"></span>{{ $p->name }}
                        @if ($p->archived_at)<span class="pill bg-gray-100 text-ink-500">Archived</span>@endif
                        <x-status-pill :status="$p->status" class="ml-auto" />
                    </a></li>
                @empty
                    <li class="px-2 py-2 text-sm text-ink-500">No matching projects.</li>
                @endforelse
            </ul>

            <h2 class="mb-2 text-sm font-semibold">Tasks <span class="font-normal text-ink-500">{{ $tasks->count() }}</span></h2>
            <ul class="divide-y divide-gray-100 border-y border-gray-100">
                @forelse ($tasks as $t)
                    <li wire:key="s-{{ $t->id }}"><button wire:click="$dispatch('open-task', { id: {{ $t->id }} })" class="flex w-full items-center gap-2.5 px-2 py-2 text-left text-sm hover:bg-gray-50">
                        <span class="check {{ $t->isCompleted() ? 'check-done' : '' }}"><x-icon name="check" class="h-3 w-3" /></span>
                        <span class="flex-1 truncate {{ $t->isCompleted() ? 'text-ink-500 line-through' : '' }}">{{ $t->title }}</span>
                        <span class="hidden max-w-40 truncate rounded px-1.5 py-0.5 text-[11px] font-medium text-white sm:inline" style="background-color: {{ $t->project->color }}">{{ $t->project->name }}</span>
                        <x-avatar :user="$t->assignee" size="xs" />
                    </button></li>
                @empty
                    <li class="px-2 py-2 text-sm text-ink-500">No matching tasks.</li>
                @endforelse
            </ul>
        @endif
    </div>
</div>
