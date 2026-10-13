<div class="mx-auto max-w-6xl px-6 py-8">
    <p class="text-sm text-ink-500">{{ now()->format('l, F j') }}</p>
    <h1 class="mt-1 text-2xl font-semibold">{{ $greeting }}, {{ auth()->user()->firstName() }}</h1>

    <div class="mt-5 inline-flex flex-wrap gap-6 rounded-full bg-gray-50 px-5 py-2.5 text-sm">
        <span><strong class="text-ink-900">{{ $stats['open'] }}</strong> <span class="text-ink-500">open tasks</span></span>
        <span><strong class="{{ $stats['overdue'] ? 'text-red-600' : 'text-ink-900' }}">{{ $stats['overdue'] }}</strong> <span class="text-ink-500">overdue</span></span>
        <span><strong class="text-emerald-600">{{ $stats['doneThisWeek'] }}</strong> <span class="text-ink-500">completed this week</span></span>
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card flex flex-col">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                <h2 class="flex items-center gap-2 font-semibold"><x-avatar :user="auth()->user()" size="sm" /> My tasks</h2>
                <a href="{{ route('my-tasks') }}" wire:navigate class="text-xs font-medium text-coral-600 hover:underline">View all</a>
            </div>
            <ul class="flex-1 divide-y divide-gray-100">
                @forelse ($upcoming as $task)
                    <li wire:key="h-{{ $task->id }}" class="flex cursor-pointer items-center gap-2.5 px-5 py-2.5 hover:bg-gray-50" wire:click="$dispatch('open-task', { id: {{ $task->id }} })">
                        <button wire:click.stop="toggleComplete({{ $task->id }})" class="check {{ $task->isCompleted() ? 'check-done' : '' }}"><x-icon name="check" class="h-3 w-3" /></button>
                        <span class="flex-1 truncate text-sm {{ $task->isCompleted() ? 'text-ink-500 line-through' : '' }}"><span class="mr-1.5 font-mono text-[11px] text-ink-500">{{ $task->ticket() }}</span>{{ $task->title }}</span>
                        <span class="hidden max-w-32 truncate rounded px-1.5 py-0.5 text-[11px] font-medium text-white sm:inline" style="background-color: {{ $task->project->color }}">{{ $task->project->name }}</span>
                        <span class="w-16 text-right text-xs {{ $task->dueClass() }}">{{ $task->dueLabel() }}</span>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-ink-500">You're all caught up. 🎉</li>
                @endforelse
            </ul>
        </section>

        <section class="card">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                <h2 class="font-semibold">Projects</h2>
                <a href="{{ route('projects.index') }}" wire:navigate class="text-xs font-medium text-coral-600 hover:underline">View all</a>
            </div>
            <div class="grid gap-1 p-3 sm:grid-cols-2">
                <a href="{{ route('projects.index', ['new' => 1]) }}" wire:navigate class="flex items-center gap-3 rounded-lg p-2.5 hover:bg-gray-50">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 text-ink-500"><x-icon name="plus" /></span>
                    <span class="text-sm font-medium text-ink-700">Create project</span>
                </a>
                @foreach ($projects as $p)
                    @php($pct = $p->tasks_total ? (int) round($p->tasks_done / $p->tasks_total * 100) : 0)
                    <a wire:key="hp-{{ $p->id }}" href="{{ route('projects.show', $p) }}" wire:navigate class="flex items-center gap-3 rounded-lg p-2.5 hover:bg-gray-50">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-white" style="background-color: {{ $p->color }}"><x-icon name="list" class="h-5 w-5" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ $p->name }}</span>
                            <x-progress-bar :percent="$pct" :color="$p->color" class="mt-1.5" />
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    @if ($updates->isNotEmpty())
        <section class="mt-6">
            <h2 class="mb-3 font-semibold">Latest status updates</h2>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($updates as $u)
                    <a wire:key="hu-{{ $u->id }}" href="{{ route('projects.show', [$u->project, 'overview']) }}" wire:navigate class="card block p-4 hover:border-gray-300">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-[3px]" style="background-color: {{ $u->project->color }}"></span>
                            <span class="truncate text-xs font-medium text-ink-500">{{ $u->project->name }}</span>
                            <x-status-pill :status="$u->status" class="ml-auto" />
                        </div>
                        <p class="mt-2 font-medium">{{ $u->title }}</p>
                        @if ($u->body)<p class="mt-1 line-clamp-2 text-sm text-ink-700">{{ $u->body }}</p>@endif
                        <p class="mt-2 text-xs text-ink-500">{{ $u->user?->name }} · {{ $u->created_at->diffForHumans() }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
