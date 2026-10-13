<div>
    <a href="{{ route('portal.dashboard') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-sm text-ink-500 hover:text-ink-900"><x-icon name="chevron-left" class="h-3.5 w-3.5" /> All projects</a>

    <div class="card bg-white p-5">
        <div class="flex flex-wrap items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-white" style="background-color: {{ $p->color }}"><x-icon name="folder" class="h-6 w-6" /></span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ $p->name }}</h1>
                    <x-status-pill :status="$p->status" />
                </div>
                <p class="mt-0.5 text-sm text-ink-500">
                    @if ($p->owner)Project lead: {{ $p->owner->name }}@endif
                    @if ($p->due_date) · Target {{ $p->due_date->format('F j, Y') }}@endif
                </p>
            </div>
            <button wire:click="$set('showRequest', true)" class="btn-primary btn"><x-icon name="plus" /> Submit a request</button>
        </div>
        <div class="mt-5">
            <div class="mb-1.5 flex justify-between text-xs text-ink-500"><span>{{ $progress['completed'] }} of {{ $progress['total'] }} shared items complete</span><span>{{ $progress['percent'] }}%</span></div>
            <x-progress-bar :percent="$progress['percent']" :color="$p->color" class="h-2" />
        </div>
        <nav class="-mb-5 mt-5 flex gap-5 border-t border-gray-100 pt-3">
            @foreach (['overview' => 'Overview', 'tasks' => 'Tasks', 'docs' => 'Docs', 'files' => 'Files'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')" class="tab {{ $tab === $key ? 'tab-active' : '' }}">
                    {{ $label }}
                    @if ($key === 'docs' && $docs->count())<span class="ml-1 rounded-full bg-gray-100 px-1.5 text-[11px] text-ink-500">{{ $docs->count() }}</span>@endif
                    @if ($key === 'tasks' && $approvals->count())<span class="ml-1 rounded-full bg-violet-100 px-1.5 text-[11px] text-violet-700">{{ $approvals->count() }}</span>@endif
                </button>
            @endforeach
        </nav>
    </div>

    <div class="mt-6">
        @if ($tab === 'overview')
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    @if ($approvals->isNotEmpty())
                        <section class="rounded-xl border border-violet-200 bg-violet-50 p-5">
                            <h2 class="font-semibold text-violet-900">Needs your approval</h2>
                            <ul class="mt-3 space-y-2">
                                @foreach ($approvals as $t)
                                    <li><button wire:click="openTask({{ $t->id }})" class="flex w-full items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-left text-sm shadow-sm hover:shadow">
                                        <span class="flex-1 truncate font-medium">{{ $t->title }}</span><span class="font-medium text-violet-700">Review →</span>
                                    </button></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <section class="card bg-white p-5">
                        <h2 class="font-semibold">Project updates</h2>
                        <div class="mt-4 space-y-5">
                            @forelse ($updates as $u)
                                <article wire:key="pu-{{ $u->id }}" class="border-l-2 border-gray-200 pl-4">
                                    <div class="flex flex-wrap items-center gap-2"><x-status-pill :status="$u->status" /><h3 class="font-medium">{{ $u->title }}</h3></div>
                                    @if ($u->body)<p class="mt-1.5 whitespace-pre-line text-sm text-ink-700">{{ $u->body }}</p>@endif
                                    <p class="mt-1.5 text-xs text-ink-500">{{ $u->user?->name }} · {{ $u->created_at->format('M j, Y') }}</p>
                                </article>
                            @empty
                                <p class="text-sm text-ink-500">No updates have been shared yet.</p>
                            @endforelse
                        </div>
                    </section>
                </div>
                <div class="space-y-6">
                    @if ($p->description)
                        <section class="card bg-white p-5">
                            <h2 class="mb-2 font-semibold">About</h2>
                            <p class="whitespace-pre-line text-sm text-ink-700">{{ $p->description }}</p>
                        </section>
                    @endif
                    <section class="card bg-white p-5">
                        <h2 class="mb-3 font-semibold">Coming up</h2>
                        <ul class="space-y-2 text-sm">
                            @forelse ($upcoming as $t)
                                <li><button wire:click="openTask({{ $t->id }})" class="flex w-full items-center gap-2 text-left hover:underline"><span class="flex-1 truncate">{{ $t->title }}</span><span class="text-xs {{ $t->dueClass() }}">{{ $t->dueLabel() }}</span></button></li>
                            @empty
                                <li class="text-ink-500">Nothing scheduled.</li>
                            @endforelse
                        </ul>
                    </section>
                    <section class="card bg-white p-5">
                        <h2 class="mb-3 font-semibold">Your team</h2>
                        <ul class="space-y-2">
                            @foreach ($team as $m)
                                <li class="flex items-center gap-2 text-sm"><x-avatar :user="$m" size="sm" /><span>{{ $m->name }}</span>@if ($m->title)<span class="text-xs text-ink-500">· {{ $m->title }}</span>@endif</li>
                            @endforeach
                        </ul>
                    </section>
                </div>
            </div>
        @elseif ($tab === 'docs')
            @if ($docs->isEmpty())
                <x-empty class="bg-white" icon="list" title="No documents shared yet" text="Requirements and specs the team shares with you will appear here." />
            @else
                <div class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
                    <nav class="flex gap-1 overflow-x-auto lg:flex-col">
                        @foreach ($docs as $d)
                            <button wire:key="pd-{{ $d->id }}" wire:click="openDoc({{ $d->id }})" class="shrink-0 rounded-md px-3 py-2 text-left text-sm {{ $doc && $doc->id === $d->id ? 'bg-white font-medium shadow-sm ring-1 ring-gray-200' : 'text-ink-700 hover:bg-white' }}">
                                <span class="block max-w-52 truncate">{{ $d->title }}</span>
                                <span class="hidden text-[11px] font-normal text-ink-500 lg:block">Updated {{ $d->updated_at?->format('M j') }}</span>
                            </button>
                        @endforeach
                    </nav>
                    @if ($doc)
                        <article class="card bg-white px-6 py-6 sm:px-10">
                            <h1 class="text-2xl font-semibold">{{ $doc->title }}</h1>
                            <p class="mb-6 mt-1 text-xs text-ink-500">Last updated {{ $doc->updated_at?->format('F j, Y') }}@if ($doc->editor) by {{ $doc->editor->name }}@endif</p>
                            <div wire:key="pdc-{{ $doc->id }}-{{ $doc->updated_at?->timestamp }}" class="doc-content trix-content" x-data x-init="tfDocImages($el, 'portal-doc-{{ $doc->id }}')">{!! \App\Support\HtmlSanitizer::clean($doc->content) !!}</div>
                        </article>
                    @endif
                </div>
            @endif
        @elseif ($tab === 'tasks')
            @forelse ($sections as $section)
                <section wire:key="ps-{{ $section->id }}" class="mb-6">
                    <h2 class="mb-2 text-sm font-semibold">{{ $section->name }} <span class="font-normal text-ink-500">{{ $section->tasks->count() }}</span></h2>
                    <ul class="card divide-y divide-gray-100 bg-white">
                        @foreach ($section->tasks as $t)
                            <li wire:key="pt-{{ $t->id }}">
                                <button wire:click="openTask({{ $t->id }})" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-gray-50">
                                    <span class="check {{ $t->isCompleted() ? 'check-done' : '' }} pointer-events-none"><x-icon name="check" class="h-3 w-3" /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium {{ $t->isCompleted() ? 'text-ink-500 line-through' : '' }}"><span class="mr-1.5 font-mono text-[11px] font-normal text-ink-500">{{ $p->key }}-{{ $t->number }}</span>{{ $t->title }}</span>
                                        <span class="text-xs text-ink-500">{{ $t->isCompleted() ? 'Completed '.$t->completed_at->format('M j') : ($t->assignee ? 'With '.$t->assignee->firstName() : 'Not started') }}</span>
                                    </span>
                                    <x-approval-pill :status="$t->approval_status" />
                                    @if ($t->comments_count)<span class="flex items-center gap-0.5 text-xs text-ink-500"><x-icon name="chat" class="h-3.5 w-3.5" />{{ $t->comments_count }}</span>@endif
                                    @if ($t->due_date)<span class="w-20 text-right text-xs {{ $t->dueClass() }}">{{ $t->dueLabel() }}</span>@endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <x-empty class="bg-white" icon="list" title="No tasks shared yet" text="The team will share milestones and deliverables here as the project moves along." />
            @endforelse
        @else
            @if ($files->isEmpty())
                <x-empty class="bg-white" icon="paperclip" title="No files shared yet" />
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($files as $f)
                        <div wire:key="pf-{{ $f->id }}">
                            <x-attachment-tile :attachment="$f" group="portal-files" class="bg-white">
                                <a href="{{ $f->downloadUrl() }}" class="shrink-0 text-ink-500 hover:text-ink-900" title="Download"><x-icon name="download" class="h-3.5 w-3.5" /></a>
                            </x-attachment-tile>
                            @if ($f->task)<p class="mt-1 truncate px-0.5 text-[11px] text-ink-500">{{ $f->task->title }}</p>@endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    {{-- Task detail --}}
    @if ($openTask)
        <x-modal :title="$openTask->title" close="openTaskId" close-value="null" width="max-w-2xl">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs font-medium text-ink-700">{{ $p->key }}-{{ $openTask->number }}</span>
                @if ($openTask->isCompleted())
                    <span class="pill bg-emerald-50 text-emerald-700"><x-icon name="check" class="h-3 w-3" /> Completed {{ $openTask->completed_at->format('M j') }}</span>
                @else
                    <span class="pill bg-gray-100 text-ink-700">{{ $openTask->section?->name ?? 'Open' }}</span>
                @endif
                <x-approval-pill :status="$openTask->approval_status" />
                @if ($openTask->due_date)<span class="text-xs {{ $openTask->dueClass() }}">Due {{ $openTask->due_date->format('M j, Y') }}</span>@endif
                @if ($openTask->assignee)<span class="flex items-center gap-1 text-xs text-ink-500"><x-avatar :user="$openTask->assignee" size="xs" /> {{ $openTask->assignee->name }}</span>@endif
            </div>

            @if ($openTask->description)
                <p class="mt-4 whitespace-pre-line text-sm text-ink-800">{{ $openTask->description }}</p>
            @endif

            @if ($openTask->subtasks->isNotEmpty())
                <ul class="mt-4 space-y-1.5">
                    @foreach ($openTask->subtasks as $sub)
                        <li class="flex items-center gap-2 text-sm"><span class="check {{ $sub->isCompleted() ? 'check-done' : '' }} h-4 w-4"><x-icon name="check" class="h-2.5 w-2.5" /></span><span class="{{ $sub->isCompleted() ? 'text-ink-500 line-through' : '' }}">{{ $sub->title }}</span></li>
                    @endforeach
                </ul>
            @endif

            @if ($openTask->approval_status === 'pending')
                <div class="mt-5 rounded-lg border border-violet-200 bg-violet-50 p-4">
                    <p class="font-medium text-violet-900">The team is asking for your approval.</p>
                    <textarea wire:model="feedback" rows="2" placeholder="Optional note — required if you're requesting changes" class="input mt-2 bg-white"></textarea>
                    @error('feedback')<p class="error">{{ $message }}</p>@enderror
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button wire:click="approve" class="btn bg-emerald-600 text-white hover:bg-emerald-700"><x-icon name="check" /> Approve</button>
                        <button wire:click="requestChanges" class="btn-secondary btn">Request changes</button>
                    </div>
                </div>
            @endif

            <div class="mt-5">
                <div class="mb-1 flex items-center justify-between">
                    <p class="label mb-0">Files</p>
                    <label class="btn-ghost btn cursor-pointer py-1 text-xs"><x-icon name="upload" /> Upload<input type="file" wire:model="uploads" multiple class="hidden"></label>
                </div>
                <span wire:loading wire:target="uploads" class="text-xs text-ink-500">Uploading…</span>
                @error('uploads.*')<p class="error">{{ $message }}</p>@enderror
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @forelse ($openTask->ownAttachments as $a)
                        <x-attachment-tile wire:key="pa-{{ $a->id }}" :attachment="$a" group="portal-task-{{ $openTask->id }}" />
                    @empty
                        <p class="col-span-full text-sm text-ink-500">No files.</p>
                    @endforelse
                </div>
            </div>

            <div class="mt-5 border-t border-gray-100 pt-4">
                <p class="label">Conversation</p>
                <ul class="space-y-3">
                    @forelse ($openTask->comments as $c)
                        <li class="flex gap-2.5">
                            <x-avatar :user="$c->user" size="sm" />
                            <div class="min-w-0 flex-1 rounded-lg bg-gray-50 px-3 py-2">
                                <p class="text-xs"><span class="font-semibold">{{ $c->user?->name ?? 'Deleted user' }}</span> <span class="text-ink-500">{{ $c->created_at->diffForHumans() }}</span></p>
                                @if ($c->body !== '')<p class="mt-0.5 whitespace-pre-line break-words text-sm">{{ $c->body }}</p>@endif
                                @if ($c->attachments->isNotEmpty())
                                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        @foreach ($c->attachments as $ca)
                                            <x-attachment-tile wire:key="pca-{{ $ca->id }}" :attachment="$ca" group="portal-task-{{ $openTask->id }}" size="sm" />
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-ink-500">No messages yet. Ask a question below.</li>
                    @endforelse
                </ul>
                <form wire:submit="addComment" class="mt-3"
                      x-data="{ drag: false }"
                      x-on:dragover.prevent="drag = true" x-on:dragleave.prevent="drag = false"
                      x-on:drop.prevent="drag = false; $refs.pcpicker.files = $event.dataTransfer.files; $refs.pcpicker.dispatchEvent(new Event('change'))"
                      x-on:paste="if ($event.clipboardData.files.length) { $refs.pcpicker.files = $event.clipboardData.files; $refs.pcpicker.dispatchEvent(new Event('change')) }">
                    <div class="rounded-md border transition" :class="drag ? 'border-coral-400 ring-2 ring-coral-100' : 'border-gray-300'">
                        <textarea wire:model="comment" rows="2" placeholder="Write a message to the team… (drop or paste images here)" class="block w-full resize-none rounded-t-md border-0 px-3 py-2 text-sm focus:ring-0 focus:outline-none"></textarea>
                        <x-upload-queue :files="$commentFiles" remove="removeCommentFile" class="px-3 pb-2" />
                        <div class="flex items-center gap-2 border-t border-gray-100 px-2 py-1">
                            <label class="flex cursor-pointer items-center gap-1 rounded px-1.5 py-1 text-xs text-ink-500 hover:bg-gray-100 hover:text-ink-900">
                                <x-icon name="paperclip" class="h-4 w-4" /> Attach
                                <input type="file" x-ref="pcpicker" wire:model="newCommentFiles" multiple class="hidden">
                            </label>
                            <span wire:loading wire:target="newCommentFiles" class="text-xs text-ink-500">Uploading…</span>
                        </div>
                    </div>
                    @error('comment')<p class="error">{{ $message }}</p>@enderror
                    @error('newCommentFiles.*')<p class="error">{{ $message }}</p>@enderror
                    <div class="mt-2 flex justify-end"><button class="btn-primary btn" wire:loading.attr="disabled" wire:target="addComment,newCommentFiles">Send</button></div>
                </form>
            </div>
        </x-modal>
    @endif

    {{-- New request --}}
    @if ($showRequest)
        <x-modal title="Submit a request" close="showRequest" width="max-w-xl">
            <form wire:submit="submitRequest" class="space-y-4">
                <div>
                    <label class="label">What do you need?</label>
                    <input wire:model="requestTitle" class="input" placeholder="e.g. Update the pricing page copy" autofocus>
                    @error('requestTitle')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Details</label>
                    <textarea wire:model="requestDetails" rows="4" class="input" placeholder="Context, links, examples…"></textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Needed by <span class="normal-case text-ink-300">(optional)</span></label>
                        <input type="date" wire:model="requestDue" class="input">
                        @error('requestDue')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label">Attachments</label>
                        <input type="file" wire:model="requestFiles" multiple class="block w-full text-sm file:mr-2 file:rounded file:border-0 file:bg-gray-100 file:px-2 file:py-1.5 file:text-sm">
                        @error('requestFiles.*')<p class="error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showRequest', false)" class="btn-secondary btn">Cancel</button>
                    <button class="btn-primary btn" wire:loading.attr="disabled" wire:target="requestFiles">Submit request</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
