<div>
@if ($task)
    <div class="fixed inset-0 z-40 bg-ink-950/20 lg:bg-transparent" wire:click="close"></div>
    <aside class="fixed inset-y-0 right-0 z-50 flex w-full max-w-xl flex-col border-l border-gray-200 bg-white shadow-2xl"
           x-data x-on:keydown.escape.window="$wire.close()">
        {{-- Toolbar --}}
        <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-2.5">
            @if ($canEdit)
                <button wire:click="toggleComplete" class="btn {{ $task->isCompleted() ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'btn-secondary' }}">
                    <x-icon name="check" /> {{ $task->isCompleted() ? 'Completed' : 'Mark complete' }}
                </button>
            @endif
            <span wire:loading class="text-xs text-ink-500">Saving…</span>
            <div class="ml-auto flex items-center gap-1">
                <a href="{{ route('projects.show', $task->project) }}" wire:navigate class="btn-ghost btn text-xs" title="Open project">
                    <span class="h-2 w-2 rounded-sm" style="background-color: {{ $task->project->color }}"></span>
                    <span class="max-w-32 truncate">{{ $task->project->name }}</span>
                </a>
                @if ($canEdit)
                    <button wire:click="deleteTask" wire:confirm="Delete this task{{ $task->subtasks->count() ? ' and its subtasks' : '' }}?" class="btn-ghost btn px-1.5 text-red-600" title="Delete task"><x-icon name="trash" /></button>
                @endif
                <button wire:click="close" class="btn-ghost btn px-1.5" title="Close"><x-icon name="x" class="h-5 w-5" /></button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto">
            <div class="px-6 py-5">
                @if ($task->parent)
                    <button wire:click="open({{ $task->parent->id }})" class="mb-2 flex items-center gap-1 text-xs text-ink-500 hover:text-ink-900">
                        <x-icon name="chevron-left" class="h-3 w-3" /> {{ $task->parent->title }}
                    </button>
                @endif

                <textarea wire:model.live.blur="title" rows="1" x-data x-init="$el.style.height = $el.scrollHeight + 'px'" x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                          @disabled(! $canEdit)
                          class="w-full resize-none rounded-md border border-transparent px-1 py-0.5 text-xl font-semibold hover:border-gray-200 focus:border-coral-400 focus:outline-none {{ $task->isCompleted() ? 'text-ink-500 line-through' : '' }}"></textarea>

                @if ($task->requested_by_client)
                    <p class="mt-1 inline-flex items-center gap-1 rounded bg-sky-50 px-2 py-0.5 text-xs text-sky-700"><x-icon name="briefcase" class="h-3 w-3" /> Requested by the client</p>
                @endif

                {{-- Fields --}}
                <dl class="mt-4 grid grid-cols-[110px_minmax(0,1fr)] items-center gap-x-3 gap-y-2.5 text-sm">
                    <dt class="text-ink-500">Assignee</dt>
                    <dd class="flex items-center gap-2">
                        <x-avatar :user="$task->assignee" size="sm" />
                        <select wire:model.live="assignee_id" @disabled(! $canEdit) class="input input-sm max-w-56">
                            <option value="">No assignee</option>
                            @foreach ($people as $p)<option value="{{ $p->id }}">{{ $p->name }}{{ $p->id === auth()->id() ? ' (me)' : '' }}</option>@endforeach
                        </select>
                    </dd>

                    <dt class="text-ink-500">Due date</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <input type="date" wire:model.live="due_date" @disabled(! $canEdit) class="input input-sm w-40">
                        @if ($task->dueLabel())<span class="text-xs {{ $task->dueClass() }}">{{ $task->dueLabel() }}</span>@endif
                    </dd>

                    <dt class="text-ink-500">Start date</dt>
                    <dd><input type="date" wire:model.live="start_date" @disabled(! $canEdit) class="input input-sm w-40"></dd>

                    @unless ($task->parent_id)
                        <dt class="text-ink-500">Section</dt>
                        <dd>
                            <select wire:model.live="section_id" @disabled(! $canEdit) class="input input-sm max-w-56">
                                @foreach ($task->project->sections as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                            </select>
                        </dd>
                    @endunless

                    <dt class="text-ink-500">Priority</dt>
                    <dd class="flex gap-1">
                        @foreach (\App\Models\Task::PRIORITIES as $k => $label)
                            <button type="button" @if ($canEdit) wire:click="$set('priority', '{{ $k }}')" @endif
                                    class="pill {{ $priority === $k ? ($k === 'none' ? 'bg-ink-900 text-white' : \App\Models\Task::PRIORITY_CLASSES[$k].' ring-1 ring-current') : 'bg-gray-100 text-ink-500 hover:bg-gray-200' }}">{{ $label }}</button>
                        @endforeach
                    </dd>

                    @if ($task->project->client_id)
                        <dt class="text-ink-500">Client</dt>
                        <dd class="flex flex-wrap items-center gap-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="client_visible" @disabled(! $canEdit) class="rounded border-gray-300 text-coral-500 focus:ring-coral-400">
                                <span>{{ $client_visible ? 'Visible in portal' : 'Internal only' }}</span>
                            </label>
                            @if ($client_visible && $canEdit)
                                @if ($task->approval_status === 'pending')
                                    <x-approval-pill status="pending" />
                                    <button wire:click="cancelApproval" class="text-xs text-ink-500 hover:underline">Cancel request</button>
                                @else
                                    <x-approval-pill :status="$task->approval_status" />
                                    <button wire:click="requestApproval" class="btn-secondary btn py-1 text-xs"><x-icon name="thumb-up" class="h-3.5 w-3.5" /> {{ $task->approval_status ? 'Ask again' : 'Request approval' }}</button>
                                @endif
                            @endif
                        </dd>
                    @endif
                </dl>

                {{-- Description --}}
                <div class="mt-5">
                    <p class="label">Description</p>
                    <textarea wire:model.live.blur="description" rows="4" @disabled(! $canEdit) placeholder="Add more detail to this task…" class="input"></textarea>
                </div>

                {{-- Subtasks --}}
                @unless ($task->parent_id)
                    <div class="mt-5">
                        <p class="label">Subtasks @if ($task->subtasks->count())<span class="normal-case text-ink-300">· {{ $task->subtasks->whereNotNull('completed_at')->count() }}/{{ $task->subtasks->count() }}</span>@endif</p>
                        <ul class="divide-y divide-gray-100 rounded-md border border-gray-200">
                            @foreach ($task->subtasks as $sub)
                                <li wire:key="sub-{{ $sub->id }}" class="group flex items-center gap-2 px-2.5 py-1.5 text-sm">
                                    <button wire:click="toggleComplete({{ $sub->id }})" class="check {{ $sub->isCompleted() ? 'check-done' : '' }}"><x-icon name="check" class="h-3 w-3" /></button>
                                    <button wire:click="open({{ $sub->id }})" class="flex-1 truncate text-left hover:underline {{ $sub->isCompleted() ? 'text-ink-500 line-through' : '' }}">{{ $sub->title }}</button>
                                    @if ($sub->due_date)<span class="text-xs {{ $sub->dueClass() }}">{{ $sub->dueLabel() }}</span>@endif
                                    <x-avatar :user="$sub->assignee" size="xs" />
                                    @if ($canEdit)<button wire:click="deleteSubtask({{ $sub->id }})" class="invisible text-ink-500 hover:text-red-600 group-hover:visible"><x-icon name="x" class="h-3.5 w-3.5" /></button>@endif
                                </li>
                            @endforeach
                            @if ($canEdit)
                                <li class="flex items-center gap-2 px-2.5 py-1.5">
                                    <x-icon name="plus" class="h-4 w-4 text-ink-300" />
                                    <input wire:model="newSubtask" wire:keydown.enter="addSubtask" placeholder="Add subtask…" class="w-full border-0 bg-transparent p-0 text-sm focus:ring-0 focus:outline-none">
                                </li>
                            @endif
                        </ul>
                    </div>
                @endunless

                {{-- Attachments --}}
                <div class="mt-5">
                    <div class="mb-1 flex items-center justify-between">
                        <p class="label mb-0">Attachments</p>
                        @if ($canEdit)
                            <label class="btn-ghost btn cursor-pointer py-1 text-xs"><x-icon name="paperclip" /> Attach
                                <input type="file" wire:model="uploads" multiple class="hidden">
                            </label>
                        @endif
                    </div>
                    <span wire:loading wire:target="uploads" class="text-xs text-ink-500">Uploading…</span>
                    @error('uploads.*')<p class="error">{{ $message }}</p>@enderror
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($task->attachments as $a)
                            <div wire:key="att-{{ $a->id }}" class="group flex items-center gap-2 rounded-md border border-gray-200 p-2">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-gray-100 text-[10px] font-bold text-ink-500">{{ \Illuminate\Support\Str::limit($a->extension(), 4, '') }}</span>
                                <a href="{{ route('attachments.download', [$a, 'inline' => 1]) }}" target="_blank" class="min-w-0 flex-1">
                                    <span class="block truncate text-sm hover:underline">{{ $a->original_name }}</span>
                                    <span class="text-xs text-ink-500">{{ $a->humanSize() }} · {{ $a->user?->firstName() }}</span>
                                </a>
                                @if ($canEdit)<button wire:click="deleteAttachment({{ $a->id }})" wire:confirm="Delete this file?" class="invisible text-ink-500 hover:text-red-600 group-hover:visible"><x-icon name="trash" class="h-3.5 w-3.5" /></button>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Activity & comments --}}
            <div class="border-t border-gray-200 bg-gray-50 px-6 py-5">
                <p class="label">Activity</p>
                <ul class="space-y-3">
                    @foreach ($timeline as $entry)
                        @if ($entry['type'] === 'comment')
                            @php($c = $entry['item'])
                            <li wire:key="cm-{{ $c->id }}" class="group flex gap-2.5">
                                <x-avatar :user="$c->user" size="sm" />
                                <div class="min-w-0 flex-1 rounded-lg border px-3 py-2 {{ $c->is_internal ? 'border-gray-200 bg-white' : 'border-emerald-200 bg-emerald-50/50' }}">
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="font-semibold text-ink-900">{{ $c->user?->name ?? 'Deleted user' }}</span>
                                        @if (in_array((int) $c->user_id, $clientUserIds, true))<span class="pill bg-sky-100 text-sky-700">Client</span>
                                        @elseif (! $c->is_internal)<span class="pill bg-emerald-100 text-emerald-700">Visible to client</span>@endif
                                        <span class="text-ink-500">{{ $c->created_at->diffForHumans() }}</span>
                                        @if ($c->user_id === auth()->id() || auth()->user()->isAdmin())
                                            <button wire:click="deleteComment({{ $c->id }})" wire:confirm="Delete this comment?" class="invisible ml-auto text-ink-500 hover:text-red-600 group-hover:visible"><x-icon name="trash" class="h-3 w-3" /></button>
                                        @endif
                                    </div>
                                    <p class="mt-1 whitespace-pre-line break-words text-sm text-ink-800">{{ $c->body }}</p>
                                </div>
                            </li>
                        @else
                            @php($a = $entry['item'])
                            <li wire:key="ac-{{ $a->id }}" class="flex items-center gap-2 pl-1 text-xs text-ink-500">
                                <span class="h-1.5 w-1.5 rounded-full bg-gray-300"></span>
                                <span><span class="font-medium text-ink-700">{{ $a->user?->name ?? 'Someone' }}</span> {{ $a->description() }} · {{ $a->created_at->diffForHumans() }}</span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Comment box --}}
        <form wire:submit="addComment" class="border-t border-gray-200 bg-white p-4">
            <textarea wire:model="comment" rows="2" placeholder="Ask a question or post an update…" class="input" x-on:keydown.meta.enter="$wire.addComment()" x-on:keydown.ctrl.enter="$wire.addComment()"></textarea>
            @error('comment')<p class="error">{{ $message }}</p>@enderror
            <div class="mt-2 flex items-center justify-between gap-2">
                @if ($task->client_visible)
                    <label class="flex items-center gap-2 text-xs text-ink-700">
                        <input type="checkbox" wire:model="commentForClient" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Visible to client
                    </label>
                @else
                    <span class="text-xs text-ink-500">Internal comment</span>
                @endif
                <button class="btn-primary btn">Comment</button>
            </div>
        </form>
    </aside>
@endif
</div>
