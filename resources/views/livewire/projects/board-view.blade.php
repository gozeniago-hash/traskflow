@php($canSections = auth()->user()->can('manageSections', $project))
<div>
    @include('livewire.projects.partials-toolbar')

    <div class="flex items-start gap-3 overflow-x-auto px-6 pb-10" @if ($canSections) x-data x-init="tfSectionSort($el, $wire)" @endif>
        @foreach ($sections as $section)
            <div wire:key="col-{{ $section->id }}" data-section-key="{{ $section->id }}" class="flex w-72 shrink-0 flex-col rounded-xl bg-gray-50 p-2">
                <div class="group flex items-center gap-1.5 px-1.5 pb-2 pt-1" x-data="{ menu: false }">
                    @if ($canSections && $sections->count() > 1)
                        <span data-section-grip title="Drag to reorder columns" class="-ml-1 cursor-grab text-ink-300 hover:text-ink-500 active:cursor-grabbing"><x-icon name="grip" class="h-4 w-4" /></span>
                    @endif
                    @if ($editingSection === $section->id)
                        <input wire:model="sectionName" wire:keydown.enter="renameSection" wire:keydown.escape="cancelSectionEdit" x-init="$el.focus(); $el.select()" x-on:blur="$wire.renameSection()" class="input input-sm font-semibold">
                    @else
                        <h3 @if ($canSections) wire:dblclick="editSection({{ $section->id }})" @endif class="truncate text-sm font-semibold">{{ $section->name }}</h3>
                        <span class="text-xs text-ink-500">{{ $section->tasks->count() }}</span>
                    @endif
                    @if ($canSections)
                    <div class="relative ml-auto">
                        <button x-on:click="menu = !menu" aria-label="Column options" :aria-expanded="menu" class="rounded p-0.5 text-ink-500 hover:bg-gray-200"><x-icon name="dots" /></button>
                        <div x-cloak x-show="menu" x-on:click.outside="menu = false" x-on:click="menu = false" class="absolute right-0 z-20 mt-1 w-44 rounded-lg bg-white p-1 text-sm shadow-lg ring-1 ring-black/5">
                            <button wire:click="editSection({{ $section->id }})" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Rename</button>
                            <button wire:click="moveSection({{ $section->id }}, -1)" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Move left</button>
                            <button wire:click="moveSection({{ $section->id }}, 1)" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Move right</button>
                            <button wire:click="deleteSection({{ $section->id }})" wire:confirm="Delete “{{ $section->name }}”? Its tasks move to another column." class="block w-full rounded px-2 py-1.5 text-left text-red-600 hover:bg-red-50">Delete</button>
                        </div>
                    </div>
                    @endif
                </div>

                <div x-data x-init="taskflowSortable($el, $wire)" data-section-id="{{ $section->id }}" data-group="board-{{ $project->id }}" class="flex min-h-[3rem] flex-col gap-2">
                    @foreach ($section->tasks as $task)
                        <div wire:key="card-{{ $task->id }}" data-task-id="{{ $task->id }}"
                             wire:click="$dispatch('open-task', { id: {{ $task->id }} })" data-peek-title="{{ $task->title }}"
                             class="cursor-pointer rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition hover:border-gray-300 hover:shadow">
                            <div class="flex items-start gap-2">
                                <button wire:click.stop="setComplete({{ $task->id }}, {{ $task->isCompleted() ? 'false' : 'true' }})" wire:loading.attr="disabled" wire:target="setComplete" aria-label="{{ $task->isCompleted() ? 'Mark incomplete' : 'Mark complete' }}" class="check mt-0.5 {{ $task->isCompleted() ? 'check-done' : '' }}"><x-icon name="check" class="h-3 w-3" /></button>
                                <div class="min-w-0">
                                    <p class="font-mono text-[11px] text-ink-500">{{ $project->key }}-{{ $task->number }}</p>
                                    <p class="text-sm font-medium leading-snug {{ $task->isCompleted() ? 'text-ink-500 line-through' : '' }}">{{ $task->title }}</p>
                                </div>
                            </div>
                            @if ($task->priority !== 'none' || $task->approval_status || $task->client_visible || ($cardFields->isNotEmpty() && $task->fieldValues->whereIn('field_id', $cardFields->pluck('id'))->isNotEmpty()))
                                <div class="mt-2 flex flex-wrap gap-1 pl-7">
                                    <x-priority-pill :priority="$task->priority" />
                                    <x-approval-pill :status="$task->approval_status" />
                                    @if ($task->client_visible)<span class="pill bg-emerald-50 text-emerald-700"><x-icon name="eye" class="h-3 w-3" /> Client</span>@endif
                                    <x-field-chips :task="$task" :fields="$cardFields" :people="$fieldPeople" />
                                </div>
                            @endif
                            <div class="mt-2.5 flex items-center gap-2 pl-7 text-xs text-ink-500">
                                <x-avatar :user="$task->assignee" size="xs" />
                                @if ($task->due_date)<span class="{{ $task->dueClass() }}">{{ $task->dueLabel() }}</span>@endif
                                <x-task-timer :task="$task" :running="$running" />
                                <span class="ml-auto flex items-center gap-2">
                                    @if ($task->subtasks_count)<span class="flex items-center gap-0.5"><x-icon name="subtask" class="h-3.5 w-3.5" />{{ $task->completed_subtasks_count }}/{{ $task->subtasks_count }}</span>@endif
                                    @if ($task->comments_count)<span class="flex items-center gap-0.5"><x-icon name="chat" class="h-3.5 w-3.5" />{{ $task->comments_count }}</span>@endif
                                    @if ($task->attachments_count)<span class="flex items-center gap-0.5"><x-icon name="paperclip" class="h-3.5 w-3.5" />{{ $task->attachments_count }}</span>@endif
                                    @if ($task->pull_requests_count)<span class="flex items-center gap-0.5 {{ $task->open_prs_count ? 'text-emerald-700' : 'text-violet-700' }}" title="{{ $task->pull_requests_count }} pull {{ \Illuminate\Support\Str::plural('request', $task->pull_requests_count) }}{{ $task->open_prs_count ? ', '.$task->open_prs_count.' open' : '' }}"><x-icon name="git-pull" class="h-3.5 w-3.5" />{{ $task->pull_requests_count }}</span>@endif
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-2" x-data="{ adding: false }">
                    <div x-show="!adding" class="flex items-center gap-1">
                        <button x-on:click="adding = true; $nextTick(() => $refs.input.focus())" class="flex flex-1 items-center gap-1.5 rounded-md px-2 py-1.5 text-sm text-ink-500 hover:bg-gray-200/60"><x-icon name="plus" /> Add task</button>
                        <button type="button" wire:click="$dispatch('create-task', { projectId: {{ $project->id }}, sectionId: {{ $section->id }} })" class="rounded-md p-1.5 text-ink-500 hover:bg-gray-200/60 hover:text-ink-900" title="Add task with details and attachments"><x-icon name="paperclip" class="h-4 w-4" /></button>
                    </div>
                    <input x-cloak x-show="adding" x-ref="input" wire:model="newTask.{{ $section->id }}" wire:keydown.enter="addTask({{ $section->id }})" x-on:keydown.escape="adding = false" x-on:blur="adding = false" placeholder="Task name, then Enter" class="input input-sm">
                </div>
            </div>
        @endforeach

        @if ($canSections)
        <form wire:submit="addSection" class="w-72 shrink-0 rounded-xl border border-dashed border-gray-300 p-2">
            <input wire:model="newSection" placeholder="+ Add section" class="w-full border-0 bg-transparent px-2 py-1.5 text-sm font-medium placeholder:text-ink-500 focus:ring-0 focus:outline-none">
        </form>
        @endif
    </div>
</div>
