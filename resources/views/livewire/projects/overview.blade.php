<div class="grid gap-6 p-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        {{-- Description --}}
        <section class="card p-5">
            <div class="mb-2 flex items-center justify-between">
                <h2 class="font-semibold">About this project</h2>
                @unless ($editingDescription)<button wire:click="$set('editingDescription', true)" class="btn-ghost btn text-xs">Edit</button>@endunless
            </div>
            @if ($editingDescription)
                <textarea wire:model="description" rows="5" class="input" placeholder="What's this project about? Goals, scope, links…"></textarea>
                <div class="mt-2 flex justify-end gap-2">
                    <button wire:click="$set('editingDescription', false)" class="btn-secondary btn">Cancel</button>
                    <button wire:click="saveDescription" class="btn-primary btn">Save</button>
                </div>
            @else
                <p class="whitespace-pre-line text-sm text-ink-700">{{ $p->description ?: 'No description yet. Add goals, scope and key links so everyone has context.' }}</p>
            @endif
        </section>

        {{-- Key resources --}}
        <section class="card p-5" x-data="{ dragging: false }"
                 @if ($canEdit) x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false"
                 x-on:drop.prevent="dragging = false; if ($event.dataTransfer.files.length) $wire.uploadMultiple('resourceFiles', $event.dataTransfer.files)" @endif
                 :class="dragging && 'ring-2 ring-coral-300 ring-offset-2'">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="font-semibold">Key resources</h2>
                @if ($canEdit)
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="$set('addingResource', 'link')" class="btn-ghost btn text-xs"><x-icon name="link" /> Add link</button>
                        <label class="btn-ghost btn cursor-pointer text-xs"><x-icon name="paperclip" /> Add file
                            <input type="file" wire:model="resourceFiles" multiple class="hidden">
                        </label>
                    </div>
                @endif
            </div>

            @if ($addingResource === 'link')
                <form wire:submit="addLink" class="mb-3 space-y-2 rounded-lg bg-gray-50 p-3"
                      x-data x-init="$nextTick(() => $refs.url.focus())" x-on:keydown.escape.stop="$wire.set('addingResource', null)">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <div>
                            <input x-ref="url" wire:model="resourceUrl" placeholder="Paste a link (Figma, Google Drive, website…)" class="input input-sm">
                            @error('resourceUrl')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <input wire:model="resourceTitle" placeholder="Title (optional)" class="input input-sm">
                            @error('resourceTitle')<p class="error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        @if ($canShare)
                            <label class="flex items-center gap-2 text-xs text-ink-700"><input type="checkbox" wire:model="resourceShare" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Show in client portal</label>
                        @else
                            <span></span>
                        @endif
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('addingResource', null)" class="btn-secondary btn py-1 text-xs">Cancel</button>
                            <button class="btn-primary btn py-1 text-xs">Add link</button>
                        </div>
                    </div>
                </form>
            @endif

            <span wire:loading wire:target="resourceFiles" class="mb-2 block text-xs text-ink-500">Uploading…</span>
            @error('resourceFiles.*')<p class="error mb-2">{{ $message }}</p>@enderror

            @if ($resources->isEmpty())
                <p class="rounded-lg border border-dashed border-gray-200 px-4 py-5 text-center text-sm text-ink-500">
                    Pin the links and files everyone keeps asking for: the brief, designs, contracts, the staging site.
                    @if ($canEdit)<br><span class="text-xs">Use Add link or Add file, or drop files here.</span>@endif
                </p>
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($resources as $r)
                        <x-resource-tile wire:key="res-{{ $r->id }}" :resource="$r" group="resources-{{ $p->id }}" :show-shared="$canShare">
                            @if ($canEdit)
                                <div class="relative" x-data="{ menu: false }">
                                    <button type="button" x-on:click="menu = !menu" class="rounded p-1 hover:bg-gray-100 hover:text-ink-900" title="More"><x-icon name="dots" /></button>
                                    <div x-cloak x-show="menu" x-on:click.outside="menu = false" x-on:click="menu = false"
                                         class="absolute right-0 z-20 mt-1 w-48 rounded-lg bg-white p-1 text-sm shadow-lg ring-1 ring-black/5">
                                        <button type="button" x-on:click="const t = prompt('Rename resource', @js($r->title)); if (t) $wire.renameResource({{ $r->id }}, t)" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Rename</button>
                                        @if ($canShare)
                                            <button type="button" wire:click="toggleResourceShare({{ $r->id }})" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">{{ $r->client_visible ? 'Hide from client portal' : 'Show in client portal' }}</button>
                                        @endif
                                        @unless ($loop->first)<button type="button" wire:click="moveResource({{ $r->id }}, -1)" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Move earlier</button>@endunless
                                        @unless ($loop->last)<button type="button" wire:click="moveResource({{ $r->id }}, 1)" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Move later</button>@endunless
                                        <button type="button" wire:click="removeResource({{ $r->id }})" wire:confirm="Remove “{{ $r->title }}”?{{ $r->isFile() ? ' The file will be deleted.' : '' }}" class="block w-full rounded px-2 py-1.5 text-left text-red-600 hover:bg-red-50">Remove</button>
                                    </div>
                                </div>
                            @endif
                        </x-resource-tile>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Progress --}}
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 2xl:grid-cols-5">
            @foreach ([
                ['Total tasks', $progress['total'], 'text-ink-900'],
                ['Completed', $progress['completed'], 'text-emerald-600'],
                ['Overdue', $progress['overdue'], $progress['overdue'] ? 'text-red-600' : 'text-ink-900'],
                ['Awaiting client', $pendingApprovals, $pendingApprovals ? 'text-violet-600' : 'text-ink-900'],
                ['Time logged', \App\Models\TimeEntry::formatShort($timeTotal), 'text-ink-900'],
            ] as [$label, $value, $color])
                <div class="card p-4">
                    <p class="text-xs text-ink-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold {{ $color }}">{{ $value }}</p>
                </div>
            @endforeach
        </section>
        <div class="card p-4">
            <div class="mb-1.5 flex justify-between text-xs text-ink-500"><span>Progress</span><span>{{ $progress['percent'] }}%</span></div>
            <x-progress-bar :percent="$progress['percent']" :color="$p->color" class="h-2" />
        </div>

        {{-- Status updates --}}
        <section class="card p-5">
            <h2 class="font-semibold">Status updates</h2>
            <form wire:submit="postUpdate" class="mt-3 space-y-3 rounded-lg bg-gray-50 p-4">
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\Project::STATUSES as $key => $label)
                        <button type="button" wire:click="$set('updateStatus', '{{ $key }}')"
                                class="pill cursor-pointer {{ $updateStatus === $key ? \App\Models\Project::STATUS_CLASSES[$key] : 'bg-white text-ink-500 ring-1 ring-gray-200' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ \App\Models\Project::STATUS_DOTS[$key] }}"></span>{{ $label }}
                        </button>
                    @endforeach
                </div>
                <input wire:model="updateTitle" placeholder="Headline, e.g. “Design phase wrapped, build starts Monday”" class="input">
                @error('updateTitle')<p class="error">{{ $message }}</p>@enderror
                <textarea wire:model="updateBody" rows="3" placeholder="What happened, what's next, any risks…" class="input"></textarea>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    @if ($p->portal_enabled)
                        <label class="flex items-center gap-2 text-sm text-ink-700">
                            <input type="checkbox" wire:model="updateShare" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Share with {{ $p->client?->name ?? 'client' }}
                        </label>
                    @else
                        <span class="text-xs text-ink-500">Internal only — client portal is off for this project.</span>
                    @endif
                    <button class="btn-primary btn">Post update</button>
                </div>
            </form>

            <div class="mt-5 space-y-5">
                @forelse ($updates as $u)
                    <article wire:key="su-{{ $u->id }}" class="group border-l-2 pl-4" style="border-color: {{ ['on_track' => '#10b981', 'at_risk' => '#f59e0b', 'off_track' => '#ef4444', 'on_hold' => '#0ea5e9', 'complete' => '#6366f1'][$u->status] ?? '#9ca3af' }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-pill :status="$u->status" />
                            <h3 class="font-medium">{{ $u->title }}</h3>
                            @if ($u->client_visible)<span class="pill bg-emerald-50 text-emerald-700"><x-icon name="eye" class="h-3 w-3" /> Shared</span>@endif
                            <button wire:click="deleteUpdate({{ $u->id }})" wire:confirm="Delete this update?" class="invisible ml-auto text-ink-500 hover:text-red-600 group-hover:visible"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                        </div>
                        @if ($u->body)<p class="mt-1.5 whitespace-pre-line text-sm text-ink-700">{{ $u->body }}</p>@endif
                        <p class="mt-1.5 text-xs text-ink-500">{{ $u->user?->name }} · {{ $u->created_at->diffForHumans() }}</p>
                    </article>
                @empty
                    <p class="text-sm text-ink-500">No updates yet. A short weekly update keeps everyone — including your client — aligned.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="space-y-6">
        <section class="card p-5">
            <h2 class="mb-3 font-semibold">Details</h2>
            <dl class="space-y-2.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Owner</dt><dd class="flex items-center gap-1.5">@if ($p->owner)<x-avatar :user="$p->owner" size="xs" />{{ $p->owner->name }}@else — @endif</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Status</dt><dd><x-status-pill :status="$p->status" /></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Team</dt><dd>{{ $p->team?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Client</dt><dd>{{ $p->client?->name ?? 'Internal' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Visibility</dt><dd class="text-right">{{ \App\Models\Project::PRIVACY[$p->privacy] ?? $p->privacy }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Start</dt><dd>{{ $p->start_date?->format('M j, Y') ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Due</dt><dd>{{ $p->due_date?->format('M j, Y') ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="card p-5">
            <h2 class="mb-3 font-semibold">Members</h2>
            <ul class="space-y-2">
                @foreach ($members as $m)
                    <li wire:key="pm-{{ $m->id }}" class="group flex items-center gap-2 text-sm">
                        <x-avatar :user="$m" size="sm" /><span class="flex-1 truncate">{{ $m->name }}</span>
                        @if ($m->id === $p->owner_id)<span class="text-xs text-ink-500">Owner</span>@else
                            <button wire:click="removeProjectMember({{ $m->id }})" class="invisible text-ink-500 hover:text-red-600 group-hover:visible"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($available->isNotEmpty())
                <div class="mt-3 flex gap-2">
                    <select wire:model="addMember" class="input input-sm">
                        <option value="">Add member…</option>
                        @foreach ($available as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                    </select>
                    <button wire:click="addProjectMember" class="btn-secondary btn">Add</button>
                </div>
            @endif
        </section>

        <section class="card p-5">
            <h2 class="mb-3 font-semibold">Coming up</h2>
            <ul class="space-y-2">
                @forelse ($upcoming as $t)
                    <li><button wire:click="$dispatch('open-task', { id: {{ $t->id }} })" data-peek-title="{{ $t->title }}" class="flex w-full items-center gap-2 text-left text-sm hover:underline">
                        <x-avatar :user="$t->assignee" size="xs" /><span class="flex-1 truncate">{{ $t->title }}</span><span class="text-xs {{ $t->dueClass() }}">{{ $t->dueLabel() }}</span>
                    </button></li>
                @empty
                    <li class="text-sm text-ink-500">Nothing scheduled.</li>
                @endforelse
            </ul>
        </section>

        <section class="card p-5">
            <h2 class="mb-3 font-semibold">Time logged by person</h2>
            <ul class="space-y-2 text-sm">
                @forelse ($timeByPerson as $row)
                    <li class="flex items-center gap-2"><x-avatar :user="$row->user" size="xs" /><span class="flex-1 truncate">{{ $row->user?->name ?? 'Former member' }}</span><span class="font-medium tabular-nums">{{ \App\Models\TimeEntry::formatShort((int) $row->seconds) }}</span></li>
                @empty
                    <li class="text-ink-500">No time logged yet. Press ▶ on any task to start a timer.</li>
                @endforelse
            </ul>
        </section>

        <section class="card p-5">
            <h2 class="mb-3 font-semibold">Open tasks by person</h2>
            <ul class="space-y-2 text-sm">
                @forelse ($byAssignee as $row)
                    <li class="flex items-center gap-2"><x-avatar :user="$row->assignee" size="xs" /><span class="flex-1 truncate">{{ $row->assignee?->name ?? 'Unassigned' }}</span><span class="font-medium">{{ $row->total }}</span></li>
                @empty
                    <li class="text-ink-500">No open tasks.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
