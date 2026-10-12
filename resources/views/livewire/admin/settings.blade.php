<div>
    <x-page-header title="Workspace settings" />
    <div class="grid max-w-5xl gap-6 p-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form wire:submit="save" class="card space-y-4 p-5">
                <h2 class="font-semibold">General</h2>
                <div>
                    <label class="label">Workspace name</label>
                    <input wire:model="name" class="input">
                    @error('name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end"><button class="btn-primary btn">Save</button></div>
            </form>

            <div class="card p-5">
                <h2 class="font-semibold">Archived projects</h2>
                <ul class="mt-3 divide-y divide-gray-100">
                    @forelse ($archived as $p)
                        <li wire:key="ar-{{ $p->id }}" class="flex items-center gap-2 py-2 text-sm">
                            <span class="h-2.5 w-2.5 rounded-[3px]" style="background-color: {{ $p->color }}"></span>
                            <span class="flex-1">{{ $p->name }}</span>
                            <span class="text-xs text-ink-500">archived {{ $p->archived_at->diffForHumans() }}</span>
                            <button wire:click="unarchive({{ $p->id }})" class="btn-ghost btn text-xs"><x-icon name="refresh" /> Restore</button>
                        </li>
                    @empty
                        <li class="py-2 text-sm text-ink-500">Nothing archived.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="card h-fit p-5">
            <h2 class="font-semibold">At a glance</h2>
            <dl class="mt-3 space-y-2 text-sm">
                @foreach ($stats as $label => $value)
                    <div class="flex justify-between"><dt class="text-ink-500">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </div>
    </div>
</div>
