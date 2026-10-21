<div>
    <x-page-header title="Clients" :subtitle="$isAdmin ? 'Companies you deliver for. Invite their contacts to the client portal.' : 'Companies you deliver for. Ask a workspace admin to add clients or invite their contacts.'">
        @if ($isAdmin)
            <x-slot:actions>
                <button wire:click="create" class="btn-primary btn"><x-icon name="plus" /> New client</button>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="space-y-4 p-6">
        @forelse ($clients as $client)
            <div wire:key="client-{{ $client->id }}" class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-ink-900 text-sm font-semibold text-white">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($client->name, 0, 2)) }}</span>
                        <div>
                            <h3 class="font-semibold">{{ $client->name }}</h3>
                            <p class="text-xs text-ink-500">
                                {{ collect([$client->contact_name, $client->email, $client->phone, $client->website])->filter()->implode(' · ') ?: 'No contact details' }}
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        @if ($isAdmin)
                            <button wire:click="openInvite({{ $client->id }})" class="btn-secondary btn text-xs"><x-icon name="mail" /> Invite to portal</button>
                            <button wire:click="edit({{ $client->id }})" class="btn-ghost btn text-xs">Edit</button>
                            <button aria-label="Delete {{ $client->name }}" wire:click="delete({{ $client->id }})" wire:confirm="Delete {{ $client->name }}? Their contacts lose portal access. Projects are kept." class="btn-ghost btn px-1.5 text-red-600"><x-icon name="trash" /></button>
                        @endif
                    </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="label">Portal contacts</p>
                        <ul class="space-y-1.5">
                            @forelse ($client->contacts as $c)
                                <li class="group flex items-center gap-2 text-sm" wire:key="c{{ $client->id }}-{{ $c->id }}">
                                    <x-avatar :user="$c" size="sm" /><span class="truncate">{{ $c->name }}</span><span class="truncate text-xs text-ink-500">{{ $c->email }}</span>
                                    @if ($isAdmin)
                                        <button aria-label="Remove {{ $c->name }}'s portal access" wire:click="removeContact({{ $client->id }}, {{ $c->id }})" wire:confirm="Remove {{ $c->name }}'s portal access?" class="invisible ml-auto text-ink-500 hover:text-red-600 group-hover:visible"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                                    @endif
                                </li>
                            @empty
                                <li class="text-sm text-ink-500">No one has portal access yet.</li>
                            @endforelse
                            @foreach ($client->invitations as $inv)
                                <li class="flex items-center gap-2 text-sm text-ink-500" wire:key="inv-{{ $inv->id }}">
                                    <x-avatar size="sm" /><span class="truncate">{{ $inv->email }}</span><span class="pill bg-amber-50 text-amber-700">Invited</span>
                                    @if ($isAdmin)
                                        <button wire:click="revokeInvite({{ $inv->id }})" class="ml-auto text-xs hover:text-red-600">Revoke</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="label">Projects ({{ $client->projects_count }})</p>
                        <ul class="space-y-1.5">
                            @forelse ($client->projects as $p)
                                <li class="flex items-center gap-2 text-sm">
                                    <span class="h-2.5 w-2.5 rounded-[3px]" style="background-color: {{ $p->color }}"></span>
                                    <a href="{{ route('projects.show', $p) }}" wire:navigate class="truncate hover:underline">{{ $p->name }}</a>
                                    @if ($p->portal_enabled)
                                        <span class="pill bg-emerald-50 text-emerald-700"><x-icon name="eye" class="h-3 w-3" /> In portal</span>
                                    @else
                                        <span class="pill bg-gray-100 text-ink-500"><x-icon name="eye-off" class="h-3 w-3" /> Hidden</span>
                                    @endif
                                </li>
                            @empty
                                <li class="text-sm text-ink-500">No active projects.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                @if ($client->notes)<p class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-ink-700">{{ $client->notes }}</p>@endif
            </div>
        @empty
            <x-empty icon="briefcase" title="No clients yet" text="Add a client, link projects to them, and invite their team to the portal.">
                @if ($isAdmin)<button wire:click="create" class="btn-primary btn"><x-icon name="plus" /> New client</button>@endif
            </x-empty>
        @endforelse
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit client' : 'New client'" close="showForm">
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="label">Company name</label>
                    <input wire:model="form.name" class="input" autofocus>
                    @error('form.name')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div><label class="label">Main contact</label><input wire:model="form.contact_name" class="input"></div>
                <div><label class="label">Email</label><input wire:model="form.email" type="email" class="input">@error('form.email')<p class="error">{{ $message }}</p>@enderror</div>
                <div><label class="label">Phone</label><input wire:model="form.phone" class="input"></div>
                <div><label class="label">Website</label><input wire:model="form.website" class="input"></div>
                <div class="sm:col-span-2"><label class="label">Internal notes</label><textarea wire:model="form.notes" rows="3" class="input"></textarea></div>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-secondary btn">Cancel</button>
                    <button class="btn-primary btn">Save</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($inviteFor)
        <x-modal title="Invite to client portal" close="closeInvite()">
            <p class="mb-4 text-sm text-ink-500">They'll see projects linked to this client that have the portal turned on — only the tasks, files and updates you share.</p>
            <form wire:submit="sendInvite" class="space-y-4">
                <div>
                    <label class="label">Email</label>
                    <input wire:model="inviteEmail" type="email" class="input" autofocus>
                    @error('inviteEmail')<p class="error">{{ $message }}</p>@enderror
                </div>
                @if ($lastInviteUrl)
                    <div class="rounded-md bg-emerald-50 p-3 text-sm" x-data>
                        <p class="font-medium text-emerald-800">Invitation sent.</p>
                        <p class="mt-1 text-emerald-700">You can also share this link directly:</p>
                        <div class="mt-2 flex gap-2">
                            <input readonly value="{{ $lastInviteUrl }}" class="input input-sm text-xs" x-ref="link">
                            <button type="button" class="btn-secondary btn text-xs" x-on:click="navigator.clipboard.writeText($refs.link.value); $dispatch('toast', { message: 'Link copied' })">Copy</button>
                        </div>
                    </div>
                @endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeInvite" class="btn-secondary btn">Done</button>
                    <button class="btn-primary btn"><x-icon name="mail" /> Send invite</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
