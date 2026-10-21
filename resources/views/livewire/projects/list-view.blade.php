@php($canManage = auth()->user()->can('update', $project))
<div>
    @include('livewire.projects.partials-toolbar')

    <div class="px-6 pb-10">
        <div class="hidden items-center gap-2 border-b border-gray-200 px-2 pb-2 text-xs font-medium text-ink-500 md:grid md:grid-cols-[minmax(0,1fr)_140px_110px_90px_104px_60px]">
            <span class="pl-7">Task name</span><span>Assignee</span><span>Due date</span><span>Priority</span><span>Time</span><span class="text-center" title="Visible in the client portal">Client</span>
        </div>

        @foreach ($sections as $section)
            @php($isCollapsed = $collapsed[$section->id] ?? false)
            <section wire:key="sec-{{ $section->id }}" class="mt-4">
                <div class="group flex items-center gap-1.5 py-1.5" x-data="{ menu: false }">
                    <button wire:click="toggleSection({{ $section->id }})" aria-label="{{ $isCollapsed ? 'Expand' : 'Collapse' }} {{ $section->name }}" aria-expanded="{{ $isCollapsed ? 'false' : 'true' }}" class="rounded p-0.5 text-ink-500 hover:bg-gray-100">
                        <x-icon name="chevron-down" class="h-4 w-4 transition {{ $isCollapsed ? '-rotate-90' : '' }}" />
                    </button>
                    @if ($editingSection === $section->id)
                        <input wire:model="sectionName" wire:keydown.enter="renameSection" wire:keydown.escape="cancelSectionEdit" x-init="$el.focus(); $el.select()" x-on:blur="$wire.renameSection()" class="input input-sm max-w-xs font-semibold">
                    @else
                        <h3 @if ($canManage) wire:dblclick="editSection({{ $section->id }})" @endif class="cursor-default font-semibold">{{ $section->name }}</h3>
                    @endif
                    <span class="text-xs text-ink-500">{{ $section->tasks->count() }}</span>
                    @if ($canManage)
                    <div class="relative ml-1">
                        <button x-on:click="menu = !menu" aria-label="Section options" :aria-expanded="menu" class="invisible rounded p-0.5 text-ink-500 hover:bg-gray-100 group-hover:visible"><x-icon name="dots" /></button>
                        <div x-cloak x-show="menu" x-on:click.outside="menu = false" x-on:click="menu = false" class="absolute left-0 z-20 mt-1 w-44 rounded-lg bg-white p-1 text-sm shadow-lg ring-1 ring-black/5">
                            <button wire:click="editSection({{ $section->id }})" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Rename section</button>
                            <button wire:click="moveSection({{ $section->id }}, -1)" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Move up</button>
                            <button wire:click="moveSection({{ $section->id }}, 1)" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Move down</button>
                            <button wire:click="deleteSection({{ $section->id }})" wire:confirm="Delete “{{ $section->name }}”? Its tasks move to another section." class="block w-full rounded px-2 py-1.5 text-left text-red-600 hover:bg-red-50">Delete section</button>
                        </div>
                    </div>
                    @endif
                </div>

                @unless ($isCollapsed)
                    <div x-data x-init="taskflowSortable($el, $wire)" data-section-id="{{ $section->id }}" data-group="list-{{ $project->id }}" class="min-h-[2px]">
                        @foreach ($section->tasks as $task)
                            <div wire:key="task-{{ $task->id }}" data-task-id="{{ $task->id }}"
                                 class="group grid cursor-pointer grid-cols-[minmax(0,1fr)] items-center gap-2 border-b border-gray-100 px-2 py-2.5 hover:bg-gray-50 md:grid-cols-[minmax(0,1fr)_140px_110px_90px_104px_60px] md:py-2"
                                 wire:click="$dispatch('open-task', { id: {{ $task->id }} })" data-peek-title="{{ $task->title }}">
                                {{-- Phones: title on its own line, tags and due date wrap underneath. --}}
                                <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 md:flex-nowrap md:overflow-hidden">
                                    <button wire:click.stop="toggleComplete({{ $task->id }})" class="check {{ $task->isCompleted() ? 'check-done' : '' }}" aria-label="{{ $task->isCompleted() ? 'Mark incomplete' : 'Mark complete' }}"><x-icon name="check" class="h-3 w-3" /></button>
                                    <span class="shrink-0 font-mono text-[11px] text-ink-500">{{ $project->key }}-{{ $task->number }}</span>
                                    <span class="min-w-0 flex-1 truncate text-sm md:flex-initial {{ $task->isCompleted() ? 'text-ink-500 line-through' : '' }}">{{ $task->title }}</span>
                                    <span class="flex min-w-0 basis-full flex-wrap items-center gap-2 pl-7 text-xs text-ink-500 empty:hidden md:shrink-[2] md:basis-auto md:flex-nowrap md:overflow-hidden md:pl-0">
                                        @if ($task->due_date)<span class="md:hidden {{ $task->dueClass() }}">{{ $task->dueLabel() }}</span>@endif
                                        @if ($task->assignee)<span class="flex items-center gap-1 md:hidden"><x-avatar :user="$task->assignee" size="xs" />{{ $task->assignee->firstName() }}</span>@endif
                                        @if ($task->subtasks_count)<span class="flex items-center gap-0.5" title="Subtasks"><x-icon name="subtask" class="h-3.5 w-3.5" />{{ $task->completed_subtasks_count }}/{{ $task->subtasks_count }}</span>@endif
                                        @if ($task->comments_count)<span class="flex items-center gap-0.5" title="Comments"><x-icon name="chat" class="h-3.5 w-3.5" />{{ $task->comments_count }}</span>@endif
                                        @if ($task->attachments_count)<span class="flex items-center gap-0.5" title="Attachments"><x-icon name="paperclip" class="h-3.5 w-3.5" />{{ $task->attachments_count }}</span>@endif
                                        @if ($task->pull_requests_count)<span class="flex items-center gap-0.5 {{ $task->open_prs_count ? 'text-emerald-700' : 'text-violet-700' }}" title="{{ $task->pull_requests_count }} pull {{ \Illuminate\Support\Str::plural('request', $task->pull_requests_count) }}{{ $task->open_prs_count ? ', '.$task->open_prs_count.' open' : '' }}"><x-icon name="git-pull" class="h-3.5 w-3.5" />{{ $task->pull_requests_count }}</span>@endif
                                        <x-approval-pill :status="$task->approval_status" />
                                        <x-field-chips :task="$task" :fields="$cardFields" :people="$fieldPeople" />
                                    </span>
                                </div>
                                <div class="hidden items-center gap-1.5 truncate text-sm md:flex">
                                    <x-avatar :user="$task->assignee" size="sm" />
                                    <span class="truncate text-ink-700">{{ $task->assignee?->firstName() }}</span>
                                </div>
                                <div class="hidden text-sm md:block {{ $task->dueClass() }}">{{ $task->dueLabel() }}</div>
                                <div class="hidden md:block"><x-priority-pill :priority="$task->priority" /></div>
                                <div class="hidden md:block"><x-task-timer :task="$task" :running="$running" /></div>
                                <div class="hidden text-center md:block">
                                    @if ($task->client_visible)<x-icon name="eye" class="mx-auto h-4 w-4 text-emerald-700" title="Visible in client portal" />@endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center gap-2 border-b border-gray-100 px-2 py-1.5">
                        <x-icon name="plus" class="ml-0.5 h-4 w-4 text-ink-300" />
                        <input wire:model="newTask.{{ $section->id }}" wire:keydown.enter="addTask({{ $section->id }})" placeholder="Add task…" class="w-full border-0 bg-transparent p-0 text-sm placeholder:text-ink-500 focus:ring-0 focus:outline-none">
                    </div>
                @endunless
            </section>
        @endforeach

        @if ($canManage)
        <form wire:submit="addSection" class="mt-6 flex max-w-sm items-center gap-2">
            <input wire:model="newSection" placeholder="Add section…" class="input input-sm">
            <button class="btn-secondary btn"><x-icon name="plus" /> Section</button>
        </form>
        @endif
    </div>
</div>
