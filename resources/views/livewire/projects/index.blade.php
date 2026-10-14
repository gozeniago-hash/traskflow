<div>
    <x-page-header title="Projects" :subtitle="$projects->count().' active '.\Illuminate\Support\Str::plural('project', $projects->count())">
        <x-slot:actions>
            @if (auth()->user()->canCreateProjects())<button wire:click="openCreate" class="btn-primary btn"><x-icon name="plus" /> New project</button>@endif
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-center gap-2 px-6 py-3">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
            <input wire:model.live.debounce.300ms="search" placeholder="Filter projects" class="input input-sm w-52 pl-7">
        </div>
        <div class="inline-flex rounded-md border border-gray-200 p-0.5 text-xs">
            <button wire:click="$set('scope', 'all')" class="rounded px-2.5 py-1 font-medium {{ $scope === 'all' ? 'bg-ink-900 text-white' : 'hover:bg-gray-100' }}">All</button>
            <button wire:click="$set('scope', 'mine')" class="rounded px-2.5 py-1 font-medium {{ $scope === 'mine' ? 'bg-ink-900 text-white' : 'hover:bg-gray-100' }}">I'm a member</button>
        </div>
        <select wire:model.live="status" class="input input-sm w-auto text-xs">
            <option value="">Any status</option>
            @foreach (\App\Models\Project::STATUSES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
        </select>
        <select wire:model.live="team" class="input input-sm w-auto text-xs">
            <option value="">All teams</option>
            @foreach ($teams as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
        </select>
    </div>

    <div class="grid gap-4 px-6 pb-10 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($projects as $p)
            @php($pct = $p->tasks_total ? (int) round($p->tasks_done / $p->tasks_total * 100) : 0)
            <a wire:key="p-{{ $p->id }}" href="{{ route('projects.show', $p) }}" wire:navigate class="card group flex flex-col p-4 transition hover:border-gray-300 hover:shadow-md">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-white" style="background-color: {{ $p->color }}"><x-icon name="list" class="h-5 w-5" /></span>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate font-semibold group-hover:underline"><span class="mr-1 font-mono text-xs font-medium text-ink-500">{{ $p->key }}</span>{{ $p->name }}</h3>
                        <p class="truncate text-xs text-ink-500">{{ $p->team?->name ?? 'No team' }}@if ($p->client) · {{ $p->client->name }}@endif</p>
                    </div>
                    <x-status-pill :status="$p->status" />
                </div>
                @if ($p->description)<p class="mt-3 line-clamp-2 text-sm text-ink-700">{{ $p->description }}</p>@endif
                <div class="mt-auto pt-4">
                    <div class="mb-1.5 flex justify-between text-xs text-ink-500">
                        <span>{{ $p->tasks_done }}/{{ $p->tasks_total }} tasks</span>
                        <span>@if ($p->due_date)Due {{ $p->due_date->format('M j') }}@endif</span>
                    </div>
                    <x-progress-bar :percent="$pct" :color="$p->color" />
                    <div class="mt-3 flex items-center justify-between">
                        <x-avatar :user="$p->owner" size="xs" />
                        @if ($p->portal_enabled)<span class="pill bg-emerald-50 text-emerald-700"><x-icon name="eye" class="h-3 w-3" /> Client portal</span>@endif
                    </div>
                </div>
            </a>
        @empty
            <x-empty class="sm:col-span-2 xl:col-span-3" icon="folder" title="No projects match" text="Create a project to start planning work with your team.">
                @if (auth()->user()->canCreateProjects())<button wire:click="openCreate" class="btn-primary btn"><x-icon name="plus" /> New project</button>@endif
            </x-empty>
        @endforelse
    </div>

    @if ($new)
        <x-modal title="New project" close="new" width="max-w-2xl">
            <form wire:submit="create" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Project name</label>
                    <input wire:model.live.debounce.400ms="form.name" class="input" autofocus placeholder="e.g. Website redesign">
                    @error('form.name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Project ID</label>
                    <input wire:model.live.debounce.400ms="form.key" maxlength="10" placeholder="Auto" class="input font-mono uppercase">
                    @error('form.key')<p class="error">{{ $message }}</p>@enderror
                    <p class="mt-1 text-[11px] text-ink-500">Tickets will be numbered {{ $form['key'] ?: 'KEY' }}-1, {{ $form['key'] ?: 'KEY' }}-2, …</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Description <span class="normal-case text-ink-300">(optional)</span></label>
                    <textarea wire:model="form.description" rows="2" class="input"></textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Starting layout</label>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ([
                            'board' => ['Kanban board', 'To do · In progress · In review · Done'],
                            'client' => ['Client delivery', 'Discovery · Design · Build · Client review · Launch'],
                            'list' => ['Simple list', 'One section — add your own'],
                        ] as $key => [$label, $hint])
                            <button type="button" wire:click="$set('form.layout', '{{ $key }}')" class="rounded-lg border p-3 text-left text-sm {{ $form['layout'] === $key ? 'border-coral-500 bg-coral-50 ring-1 ring-coral-500' : 'border-gray-200 hover:border-gray-300' }}">
                                <span class="block font-medium">{{ $label }}</span><span class="text-xs text-ink-500">{{ $hint }}</span>
                            </button>
                        @endforeach
                    </div>
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
                    <label class="label">Client</label>
                    <select wire:model.live="form.client_id" class="input">
                        <option value="">Internal project</option>
                        @foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Due date</label>
                    <input type="date" wire:model="form.due_date" class="input">
                </div>
                @if ($form['client_id'])
                    <label class="flex items-start gap-2 rounded-lg bg-gray-50 p-3 text-sm sm:col-span-2">
                        <input type="checkbox" wire:model="form.portal_enabled" class="mt-0.5 rounded border-gray-300 text-coral-500 focus:ring-coral-400">
                        <span><span class="font-medium">Show in the client portal</span> — clients only see tasks and files you mark as shared.</span>
                    </label>
                @endif
                <div class="rounded-lg border border-gray-200 p-4 sm:col-span-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium">Custom fields for tasks <span class="font-normal text-ink-500">(optional)</span></p>
                            <p class="text-xs text-ink-500">Extra details your team fills in on every task — e.g. Type, Story points, Budget.</p>
                        </div>
                        <button type="button" wire:click="addFieldRow" class="btn-secondary btn text-xs"><x-icon name="plus" /> Field</button>
                    </div>
                    @if ($form['fields'])
                        <ul class="mt-3 space-y-2">
                            @foreach ($form['fields'] as $i => $row)
                                <li wire:key="nf-{{ $i }}" class="grid gap-2 rounded-md bg-gray-50 p-2 sm:grid-cols-[minmax(0,1fr)_140px_auto]">
                                    <input wire:model="form.fields.{{ $i }}.name" placeholder="Field name" class="input input-sm">
                                    <select wire:model.live="form.fields.{{ $i }}.type" class="input input-sm">
                                        @foreach (\App\Models\ProjectField::TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                    </select>
                                    <button type="button" wire:click="removeFieldRow({{ $i }})" class="justify-self-end rounded p-1 text-ink-500 hover:text-red-600" title="Remove"><x-icon name="x" class="h-4 w-4" /></button>
                                    @if (in_array($row['type'], ['select', 'multiselect'], true))
                                        <input wire:model="form.fields.{{ $i }}.options" placeholder="Options, separated by commas (e.g. Bug, Feature, Support)" class="input input-sm sm:col-span-3">
                                    @endif
                                    @error('form.fields.'.$i.'.name')<p class="error sm:col-span-3">{{ $message }}</p>@enderror
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach (\App\Models\ProjectField::PRESETS as $key => $preset)
                            @continue(collect($form['fields'])->contains('name', $preset['name']))
                            <button type="button" wire:click="addFieldRow('{{ $key }}')" class="pill bg-gray-100 text-ink-700 hover:bg-gray-200">+ {{ $preset['name'] }}</button>
                        @endforeach
                    </div>
                    <p class="mt-2 text-[11px] text-ink-500">You can add, edit or remove fields later from the project's ⋯ menu → Custom fields.</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="label">Color</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach (\App\Models\Project::COLORS as $c)
                            <button type="button" wire:click="$set('form.color', '{{ $c }}')" class="h-7 w-7 rounded-md ring-offset-2 {{ $form['color'] === $c ? 'ring-2 ring-ink-900' : '' }}" style="background-color: {{ $c }}"></button>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('new', false)" class="btn-secondary btn">Cancel</button>
                    <button class="btn-primary btn">Create project</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
