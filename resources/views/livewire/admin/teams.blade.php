<div>
    <x-page-header title="Teams" subtitle="Group people so projects can be shared with the right crew.">
        <x-slot:actions>
            <button wire:click="create" class="btn-primary btn"><x-icon name="plus" /> New team</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($teams as $team)
            <div wire:key="team-{{ $team->id }}" class="card flex flex-col p-4">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold">{{ $team->name }}</h3>
                        <p class="text-xs text-ink-500">{{ $team->users->count() }} {{ \Illuminate\Support\Str::plural('member', $team->users->count()) }} · {{ $team->projects_count }} {{ \Illuminate\Support\Str::plural('project', $team->projects_count) }}</p>
                    </div>
                    <div class="flex gap-1">
                        <button wire:click="edit({{ $team->id }})" class="btn-ghost btn px-2 text-xs">Edit</button>
                        @if ($isAdmin)
                            <button wire:click="delete({{ $team->id }})" wire:confirm="Delete the {{ $team->name }} team? Projects stay in the workspace." class="btn-ghost btn px-1.5 text-red-600"><x-icon name="trash" /></button>
                        @endif
                    </div>
                </div>
                @if ($team->description)<p class="mt-2 text-sm text-ink-700">{{ $team->description }}</p>@endif

                <ul class="mt-3 flex-1 space-y-1.5">
                    @foreach ($team->users as $u)
                        <li wire:key="t{{ $team->id }}-u{{ $u->id }}" class="group flex items-center gap-2 text-sm">
                            <x-avatar :user="$u" size="sm" /> <span class="flex-1 truncate">{{ $u->name }}</span>
                            <button wire:click="removeMember({{ $team->id }}, {{ $u->id }})" class="invisible text-ink-500 hover:text-red-600 group-hover:visible" title="Remove"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                        </li>
                    @endforeach
                </ul>

                @php($available = $members->whereNotIn('id', $team->users->pluck('id')))
                @if ($available->isNotEmpty())
                    <div class="mt-3 flex gap-2 border-t border-gray-100 pt-3">
                        <select wire:model="addUser.{{ $team->id }}" class="input input-sm">
                            <option value="">Add a member…</option>
                            @foreach ($available as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                        </select>
                        <button wire:click="addMember({{ $team->id }})" class="btn-secondary btn">Add</button>
                    </div>
                @endif
            </div>
        @empty
            <x-empty class="md:col-span-2 xl:col-span-3" icon="users" title="No teams yet" text="Create a team for each department or squad." />
        @endforelse
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit team' : 'New team'" close="showForm">
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="label">Name</label>
                    <input wire:model="name" class="input" autofocus>
                    @error('name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Description</label>
                    <textarea wire:model="description" rows="3" class="input"></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-secondary btn">Cancel</button>
                    <button class="btn-primary btn">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
