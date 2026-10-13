<div>
    {{-- Project header --}}
    <div class="border-b border-gray-200 px-6 pt-4">
        @if ($project->archived_at)
            <div class="mb-3 flex items-center gap-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                <x-icon name="archive" /> This project is archived.
                @if ($canDelete)<button wire:click="archive" class="ml-auto font-medium underline">Restore</button>@endif
            </div>
        @endif
        <div class="flex flex-wrap items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-white" style="background-color: {{ $project->color }}"><x-icon name="list" class="h-5 w-5" /></span>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs font-semibold text-ink-700" title="Project ID — tickets are numbered {{ $project->key }}-1, {{ $project->key }}-2, …">{{ $project->key }}</span>
                    <h1 class="truncate text-xl font-semibold">{{ $project->name }}</h1>
                    <button wire:click="openEdit" class="rounded p-1 text-ink-500 hover:bg-gray-100" title="Project details"><x-icon name="chevron-down" /></button>
                </div>
                <p class="text-xs text-ink-500">
                    {{ $project->team?->name ?? 'No team' }}
                    @if ($project->client) · Client: <span class="font-medium text-ink-700">{{ $project->client->name }}</span>@endif
                    @if ($project->due_date) · Due {{ $project->due_date->format('M j, Y') }}@endif
                </p>
            </div>

            <div class="relative" x-data="{ open: false }">
                <button x-on:click="open = !open"><x-status-pill :status="$project->status" class="cursor-pointer" /></button>
                <div x-cloak x-show="open" x-on:click.outside="open = false" x-on:click="open = false" class="absolute left-0 z-30 mt-1 w-40 rounded-lg bg-white p-1 shadow-lg ring-1 ring-black/5">
                    @foreach (\App\Models\Project::STATUSES as $key => $label)
                        <button wire:click="setStatus('{{ $key }}')" class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-gray-100">
                            <span class="h-2 w-2 rounded-full {{ \App\Models\Project::STATUS_DOTS[$key] }}"></span>{{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($project->client_id)
                <span class="pill {{ $project->portal_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-ink-500' }}" title="Client portal">
                    <x-icon :name="$project->portal_enabled ? 'eye' : 'eye-off'" class="h-3 w-3" /> Portal {{ $project->portal_enabled ? 'on' : 'off' }}
                </span>
            @endif

            <div class="ml-auto flex items-center gap-2">
                <div class="flex -space-x-1.5">
                    @foreach ($members->take(5) as $m)<x-avatar :user="$m" size="sm" class="ring-2 ring-white" />@endforeach
                    @if ($members->count() > 5)<span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-[10px] ring-2 ring-white">+{{ $members->count() - 5 }}</span>@endif
                </div>
                <div class="relative" x-data="{ open: false }">
                    <button x-on:click="open = !open" class="btn-ghost btn px-1.5"><x-icon name="dots" class="h-5 w-5" /></button>
                    <div x-cloak x-show="open" x-on:click.outside="open = false" x-on:click="open = false" class="absolute right-0 z-30 mt-1 w-48 rounded-lg bg-white p-1 text-sm shadow-lg ring-1 ring-black/5">
                        <button wire:click="openEdit" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Edit project details</button>
                        <button wire:click="openFields" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">Custom fields</button>
                        @if ($canDelete)
                            <button wire:click="archive" class="block w-full rounded px-2 py-1.5 text-left hover:bg-gray-100">{{ $project->archived_at ? 'Restore project' : 'Archive project' }}</button>
                            <button wire:click="deleteProject" wire:confirm="Permanently delete “{{ $project->name }}” and all of its tasks and files?" class="block w-full rounded px-2 py-1.5 text-left text-red-600 hover:bg-red-50">Delete project</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <nav class="mt-4 flex gap-5">
            @foreach (\App\Models\Project::VIEWS as $key => $label)
                <a href="{{ route('projects.show', [$project, $key]) }}" wire:navigate class="tab {{ $view === $key ? 'tab-active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    {{-- Active view --}}
    @if ($view === 'overview')
        <livewire:projects.overview :project="$project" :key="'ov-'.$project->id" />
    @elseif ($view === 'board')
        <livewire:projects.board-view :project="$project" :key="'bd-'.$project->id" />
    @elseif ($view === 'calendar')
        <livewire:projects.calendar-view :project="$project" :key="'cal-'.$project->id" />
    @elseif ($view === 'docs')
        <livewire:projects.docs :project="$project" :key="'dc-'.$project->id" />
    @elseif ($view === 'timesheet')
        <livewire:projects.timesheet :project="$project" :key="'ts-'.$project->id" />
    @elseif ($view === 'files')
        <livewire:projects.files :project="$project" :key="'fl-'.$project->id" />
    @else
        <livewire:projects.list-view :project="$project" :key="'ls-'.$project->id" />
    @endif

    {{-- Edit details --}}
    @if ($showEdit)
        <x-modal title="Project details" close="showEdit" width="max-w-2xl">
            <form wire:submit="saveEdit" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Name</label>
                    <input wire:model="form.name" class="input">
                    @error('form.name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Project ID</label>
                    <input wire:model.live.debounce.500ms="form.key" maxlength="10" class="input font-mono uppercase">
                    @error('form.key')<p class="error">{{ $message }}</p>@enderror
                    @if (($form['key'] ?? '') !== $project->key)
                        <p class="mt-1 text-[11px] text-amber-700">All tickets will be renumbered to {{ strtoupper($form['key'] ?: '…') }}-#. Old {{ $project->key }}-# links stop working.</p>
                    @endif
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description</label>
                    <textarea wire:model="form.description" rows="3" class="input"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Color</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Models\Project::COLORS as $c)
                            <button type="button" wire:click="$set('form.color', '{{ $c }}')" class="h-7 w-7 rounded-md ring-offset-2 {{ ($form['color'] ?? '') === $c ? 'ring-2 ring-ink-900' : '' }}" style="background-color: {{ $c }}"></button>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="label">Owner</label>
                    <select wire:model="form.owner_id" class="input">
                        <option value="">—</option>
                        @foreach ($people as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Team</label>
                    <select wire:model="form.team_id" class="input">
                        <option value="">No team</option>
                        @foreach ($teams as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Who can see it</label>
                    <select wire:model="form.privacy" class="input">
                        @foreach (\App\Models\Project::PRIVACY as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Default view</label>
                    <select wire:model="form.default_view" class="input">
                        @foreach (\App\Models\Project::VIEWS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div><label class="label">Start date</label><input type="date" wire:model="form.start_date" class="input"></div>
                <div><label class="label">Due date</label><input type="date" wire:model="form.due_date" class="input">@error('form.due_date')<p class="error">{{ $message }}</p>@enderror</div>

                <div class="rounded-lg border border-gray-200 p-4 sm:col-span-2">
                    <label class="label">Client</label>
                    <select wire:model.live="form.client_id" class="input">
                        <option value="">Internal project (no client)</option>
                        @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                    @if (! empty($form['client_id']))
                        <label class="mt-3 flex items-start gap-2 text-sm">
                            <input type="checkbox" wire:model="form.portal_enabled" class="mt-0.5 rounded border-gray-300 text-coral-500 focus:ring-coral-400">
                            <span><span class="font-medium">Show in the client portal.</span> <span class="text-ink-500">Client contacts see the project, its shared status updates, and only the tasks and files you mark as visible to the client.</span></span>
                        </label>
                    @endif
                </div>

                <div class="flex items-center justify-between rounded-lg border border-gray-200 p-4 sm:col-span-2">
                    <div>
                        <p class="text-sm font-medium">Custom fields</p>
                        <p class="text-xs text-ink-500">{{ $project->fields()->count() }} configured — extra details your team fills in on each task.</p>
                    </div>
                    <button type="button" wire:click="openFields" class="btn-secondary btn">Manage fields</button>
                </div>

                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showEdit', false)" class="btn-secondary btn">Cancel</button>
                    <button class="btn-primary btn">Save changes</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($showFields)
        <x-modal title="Custom fields" close="showFields" width="max-w-2xl">
            <livewire:projects.fields :project="$project" :key="'fields-'.$project->id" />
        </x-modal>
    @endif
</div>
