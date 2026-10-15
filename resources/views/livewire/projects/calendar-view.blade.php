<div class="px-6 pb-10 pt-3">
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <button wire:click="today" class="btn-secondary btn">Today</button>
        <button wire:click="shift(-1)" class="btn-ghost btn px-1.5" aria-label="Previous month"><x-icon name="chevron-left" /></button>
        <button wire:click="shift(1)" class="btn-ghost btn px-1.5" aria-label="Next month"><x-icon name="chevron-right" /></button>
        <h2 class="text-lg font-semibold">{{ $start->format('F Y') }}</h2>
        @if ($unscheduled)
            <span class="ml-auto text-xs text-ink-500">{{ $unscheduled }} open {{ \Illuminate\Support\Str::plural('task', $unscheduled) }} without a due date</span>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200">
        <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-medium text-ink-500">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)<div class="py-2">{{ $d }}</div>@endforeach
        </div>
        <div class="grid grid-cols-7">
            @foreach ($days as $day)
                @php($key = $day->toDateString())
                <div wire:key="day-{{ $key }}" class="group min-h-28 border-b border-r border-gray-100 p-1.5 {{ $day->month !== $start->month ? 'bg-gray-50/70' : '' }}">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs {{ $day->isToday() ? 'bg-coral-600 font-semibold text-white' : ($day->month !== $start->month ? 'text-ink-300' : 'text-ink-700') }}">{{ $day->day }}</span>
                        <span class="invisible flex gap-0.5 group-hover:visible">
                            <button wire:click="startAdding('{{ $key }}')" class="rounded p-0.5 text-ink-500 hover:bg-gray-100" title="Quick add"><x-icon name="plus" class="h-3.5 w-3.5" /></button>
                            <button wire:click="$dispatch('create-task', { projectId: {{ $project->id }}, dueDate: '{{ $key }}' })" class="rounded p-0.5 text-ink-500 hover:bg-gray-100" title="Add with details and attachments"><x-icon name="paperclip" class="h-3.5 w-3.5" /></button>
                        </span>
                    </div>
                    <div class="space-y-1">
                        @foreach ($tasks[$key] ?? [] as $task)
                            <button wire:key="ct-{{ $task->id }}" wire:click="$dispatch('open-task', { id: {{ $task->id }} })" data-peek-title="{{ $task->title }}"
                                    class="flex w-full items-center gap-1 truncate rounded px-1.5 py-1 text-left text-xs {{ $task->isCompleted() ? 'bg-gray-100 text-ink-500 line-through' : 'text-white' }}"
                                    @unless ($task->isCompleted()) style="background-color: {{ $project->color }}" @endunless>
                                <span class="truncate" title="{{ $project->key }}-{{ $task->number }} · {{ $task->title }}">{{ $task->title }}</span>
                            </button>
                        @endforeach
                        @if ($addingOn === $key)
                            <input wire:model="newTitle" wire:keydown.enter="createOnDay" wire:keydown.escape="$set('addingOn', null)" x-init="$el.focus()" placeholder="Task name" class="input input-sm text-xs">
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
