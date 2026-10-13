<div>
    @if (! $showForm)
        <p class="mb-4 text-sm text-ink-500">Custom fields appear on every task in <strong class="text-ink-900">{{ $project->name }}</strong> — in the New task form, the task panel and, if you choose, on cards.</p>

        @if ($fields->isNotEmpty())
            <ul class="mb-5 divide-y divide-gray-100 rounded-lg border border-gray-200">
                @foreach ($fields as $f)
                    <li wire:key="pf-{{ $f->id }}" class="flex items-center gap-3 px-3 py-2.5">
                        <div class="flex flex-col">
                            <button wire:click="move({{ $f->id }}, -1)" class="text-ink-300 hover:text-ink-900" title="Move up" @disabled($loop->first)><x-icon name="chevron-down" class="h-3 w-3 rotate-180" /></button>
                            <button wire:click="move({{ $f->id }}, 1)" class="text-ink-300 hover:text-ink-900" title="Move down" @disabled($loop->last)><x-icon name="chevron-down" class="h-3 w-3" /></button>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ $f->name }}@if ($f->required)<span class="text-red-600"> *</span>@endif</p>
                            <p class="text-xs text-ink-500">
                                {{ \App\Models\ProjectField::TYPES[$f->type] ?? $f->type }}
                                @if ($f->hasOptions()) · {{ count($f->optionList()) }} options @endif
                                · used on {{ $f->values_count }} {{ \Illuminate\Support\Str::plural('task', $f->values_count) }}
                                @if ($f->show_on_card) · on cards @endif
                                @if ($f->client_visible) · <span class="text-emerald-700">visible to client</span>@endif
                            </p>
                            @if ($f->hasOptions())
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($f->optionList() as $o)<span class="pill {{ \App\Models\ProjectField::COLORS[$o['color']] ?? '' }}">{{ $o['label'] }}</span>@endforeach
                                </div>
                            @endif
                        </div>
                        <button wire:click="edit({{ $f->id }})" class="btn-ghost btn text-xs">Edit</button>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="create" class="btn-primary btn"><x-icon name="plus" /> New field</button>
            <span class="text-xs text-ink-500">or add a common one:</span>
            @foreach (\App\Models\ProjectField::PRESETS as $key => $preset)
                @continue($fields->contains('name', $preset['name']))
                <button wire:click="addPreset('{{ $key }}')" class="pill bg-gray-100 text-ink-700 hover:bg-gray-200">+ {{ $preset['name'] }}</button>
            @endforeach
        </div>
    @else
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label">Field name</label>
                    <input wire:model="form.name" class="input" autofocus placeholder="e.g. Story points">
                    @error('form.name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Type</label>
                    @if ($editing)
                        <p class="input bg-gray-50 text-ink-700">{{ \App\Models\ProjectField::TYPES[$editing->type] }}</p>
                        <p class="mt-1 text-[11px] text-ink-500">The type can't change once tasks may have values.</p>
                    @else
                        <select wire:model.live="form.type" class="input">
                            @foreach (\App\Models\ProjectField::TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select>
                    @endif
                </div>
            </div>
            <div>
                <label class="label">Help text <span class="normal-case text-ink-300">(optional)</span></label>
                <input wire:model="form.description" maxlength="200" class="input" placeholder="Shown as a hint when filling in the field">
            </div>

            @php $type = $editing?->type ?? $form['type']; @endphp
            @if (in_array($type, ['select', 'multiselect'], true))
                <div>
                    <label class="label">Options</label>
                    <ul class="space-y-1.5">
                        @foreach ($form['options'] as $i => $o)
                            <li wire:key="opt-{{ $o['id'] }}" class="flex items-center gap-2" x-data="{ pick: false }">
                                <div class="relative">
                                    <button type="button" x-on:click="pick = !pick" class="h-6 w-6 rounded-full ring-2 ring-white {{ \App\Models\ProjectField::SWATCHES[$o['color']] ?? 'bg-gray-400' }}" title="Color"></button>
                                    <div x-cloak x-show="pick" x-on:click.outside="pick = false" class="absolute left-0 z-20 mt-1 grid w-36 grid-cols-5 gap-1.5 rounded-lg bg-white p-2 shadow-lg ring-1 ring-black/5">
                                        @foreach (\App\Models\ProjectField::SWATCHES as $c => $cls)
                                            <button type="button" wire:click="setOptionColor({{ $i }}, '{{ $c }}')" x-on:click="pick = false" class="h-5 w-5 rounded-full {{ $cls }}"></button>
                                        @endforeach
                                    </div>
                                </div>
                                <input wire:model="form.options.{{ $i }}.label" class="input input-sm flex-1">
                                <button type="button" wire:click="moveOption({{ $i }}, -1)" class="text-ink-300 hover:text-ink-900"><x-icon name="chevron-down" class="h-3.5 w-3.5 rotate-180" /></button>
                                <button type="button" wire:click="moveOption({{ $i }}, 1)" class="text-ink-300 hover:text-ink-900"><x-icon name="chevron-down" class="h-3.5 w-3.5" /></button>
                                <button type="button" wire:click="removeOption({{ $i }})" class="text-ink-500 hover:text-red-600" title="Remove"><x-icon name="x" class="h-4 w-4" /></button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-2 flex gap-2">
                        <input wire:model="newOption" wire:keydown.enter.prevent="addOption" placeholder="Add an option and press Enter" class="input input-sm">
                        <button type="button" wire:click="addOption" class="btn-secondary btn">Add</button>
                    </div>
                    @error('newOption')<p class="error">{{ $message }}</p>@enderror
                    @error('form.options.*.label')<p class="error">{{ $message }}</p>@enderror
                    @if ($editing)<p class="mt-1 text-[11px] text-ink-500">Renaming an option updates every task that uses it. Removing one clears it from those tasks.</p>@endif
                </div>
            @endif

            <div class="space-y-2 rounded-lg bg-gray-50 p-3 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.required" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Required when creating a task</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.show_on_card" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Show on board cards and list rows</label>
                @if ($project->portal_enabled)
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.client_visible" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400"> Show to the client on shared tasks</label>
                @endif
            </div>

            <div class="flex items-center justify-between gap-2">
                <div>
                    @if ($editing)
                        <button type="button" wire:click="delete({{ $editing->id }})" wire:confirm="Delete “{{ $editing->name }}”? Its values are removed from every task." class="btn-ghost btn text-red-600"><x-icon name="trash" /> Delete field</button>
                    @endif
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-secondary btn">Back</button>
                    <button class="btn-primary btn">{{ $editing ? 'Save field' : 'Add field' }}</button>
                </div>
            </div>
        </form>
    @endif
</div>
