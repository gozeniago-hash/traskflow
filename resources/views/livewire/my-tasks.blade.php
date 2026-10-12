<div>
    <x-page-header title="My tasks" :subtitle="$total.' '.\Illuminate\Support\Str::plural('task', $total).($show === 'completed' ? ' completed' : ' to do')">
        <x-slot:actions>
            <div class="inline-flex rounded-md border border-gray-200 p-0.5 text-xs">
                <button wire:click="$set('show', 'incomplete')" class="rounded px-2.5 py-1 font-medium {{ $show === 'incomplete' ? 'bg-ink-900 text-white' : 'hover:bg-gray-100' }}">To do</button>
                <button wire:click="$set('show', 'completed')" class="rounded px-2.5 py-1 font-medium {{ $show === 'completed' ? 'bg-ink-900 text-white' : 'hover:bg-gray-100' }}">Completed</button>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="px-6 pb-10">
        @if ($projects->isNotEmpty())
            <form wire:submit="addTask" class="mt-4 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 p-2">
                <x-icon name="plus" class="ml-1 h-4 w-4 text-ink-300" />
                <input wire:model="newTitle" placeholder="Add a task for yourself…" class="min-w-48 flex-1 border-0 bg-transparent p-1 text-sm focus:ring-0 focus:outline-none">
                <select wire:model="newProject" class="input input-sm w-auto text-xs">
                    @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
                <input type="date" wire:model="newDue" class="input input-sm w-36 text-xs">
                <button class="btn-primary btn py-1 text-xs">Add</button>
            </form>
            @error('newProject')<p class="error">{{ $message }}</p>@enderror
        @endif

        @forelse ($groups as $label => $tasks)
            <section class="mt-6" wire:key="g-{{ \Illuminate\Support\Str::slug($label) }}">
                <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold {{ $label === 'Overdue' ? 'text-red-600' : '' }}">{{ $label }} <span class="font-normal text-ink-500">{{ count($tasks) }}</span></h2>
                <ul class="divide-y divide-gray-100 border-y border-gray-100">
                    @foreach ($tasks as $task)
                        <li wire:key="mt-{{ $task->id }}" class="flex cursor-pointer items-center gap-2.5 px-2 py-2 hover:bg-gray-50" wire:click="$dispatch('open-task', { id: {{ $task->id }} })">
                            <button wire:click.stop="toggleComplete({{ $task->id }})" class="check {{ $task->isCompleted() ? 'check-done' : '' }}"><x-icon name="check" class="h-3 w-3" /></button>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm {{ $task->isCompleted() ? 'text-ink-500 line-through' : '' }}">{{ $task->title }}</span>
                                @if ($task->parent)<span class="block truncate text-xs text-ink-500">↳ {{ $task->parent->title }}</span>@endif
                            </span>
                            <x-task-timer :task="$task" :running="$running" />
                            <x-priority-pill :priority="$task->priority" class="hidden sm:inline-flex" />
                            <span class="hidden max-w-40 truncate rounded px-1.5 py-0.5 text-[11px] font-medium text-white sm:inline" style="background-color: {{ $task->project->color }}">{{ $task->project->name }}</span>
                            <span class="w-20 text-right text-xs {{ $task->dueClass() }}">{{ $show === 'completed' ? $task->completed_at->format('M j') : $task->dueLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <x-empty class="mt-8" icon="check-circle" :title="$show === 'completed' ? 'Nothing completed yet' : 'No tasks assigned to you'" text="Tasks assigned to you across all projects show up here, grouped by when they're due." />
        @endforelse
    </div>
</div>
