<div>
    <x-page-header title="Members" subtitle="Everyone on your internal team. Client contacts are managed under Clients.">
        <x-slot:actions>
            @if ($isAdmin)
                <button wire:click="openInvite" class="btn-primary btn"><x-icon name="plus" /> Invite people</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="p-6">
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-ink-500">
                    <tr><th class="px-4 py-2.5 font-semibold">Name</th><th class="hidden px-4 py-2.5 font-semibold md:table-cell">Email</th><th class="px-4 py-2.5 font-semibold">Role</th><th class="hidden px-4 py-2.5 font-semibold sm:table-cell">Open tasks</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($members as $m)
                        <tr wire:key="m-{{ $m->id }}">
                            <td class="px-4 py-2.5"><div class="flex items-center gap-2.5"><x-avatar :user="$m" size="sm" /><div><p class="font-medium">{{ $m->name }}</p>@if ($m->title)<p class="text-xs text-ink-500">{{ $m->title }}</p>@endif</div></div></td>
                            <td class="hidden px-4 py-2.5 text-ink-500 md:table-cell">{{ $m->email }}</td>
                            <td class="px-4 py-2.5">
                                @if ($isAdmin && $m->pivot->role !== 'owner' && $m->id !== auth()->id())
                                    <select aria-label="Role for {{ $m->name }}" wire:change="changeRole({{ $m->id }}, $event.target.value)" class="input input-sm w-28">
                                        <option value="admin" @selected($m->pivot->role === 'admin')>Admin</option>
                                        <option value="manager" @selected($m->pivot->role === 'manager')>Manager</option>
                                        <option value="member" @selected($m->pivot->role === 'member')>Member</option>
                                    </select>
                                @else
                                    <span class="pill bg-gray-100 text-ink-700">{{ \App\Models\Workspace::ROLES[$m->pivot->role] ?? $m->pivot->role }}</span>
                                @endif
                            </td>
                            <td class="hidden px-4 py-2.5 text-ink-500 sm:table-cell">{{ $m->open_tasks_count }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @if ($isAdmin && $m->pivot->role !== 'owner' && $m->id !== auth()->id())
                                    <button wire:click="remove({{ $m->id }})" wire:confirm="Remove {{ $m->name }} from the workspace? Their tasks stay but become unassigned in your views." class="btn-ghost btn px-1.5 text-red-600" title="Remove"><x-icon name="trash" /></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($pending->isNotEmpty())
            <h2 class="mb-2 mt-8 text-sm font-semibold">Pending invitations</h2>
            <div class="card divide-y divide-gray-100">
                @foreach ($pending as $inv)
                    <div wire:key="pi-{{ $inv->id }}" class="flex flex-wrap items-center gap-3 px-4 py-2.5 text-sm" x-data>
                        <x-avatar size="sm" />
                        <span class="font-medium">{{ $inv->email }}</span>
                        <span class="pill bg-gray-100 text-ink-700">{{ \App\Models\Workspace::ROLES[$inv->role] }}</span>
                        <span class="text-xs text-ink-500">expires {{ $inv->expires_at->diffForHumans() }}</span>
                        @if ($isAdmin)
                            <span class="ml-auto flex gap-1">
                                <button class="btn-ghost btn text-xs" x-on:click="navigator.clipboard.writeText(@js($inv->url())); $dispatch('toast', { message: 'Invite link copied' })"><x-icon name="link" /> Copy link</button>
                                <button wire:click="revoke({{ $inv->id }})" class="btn-ghost btn text-xs text-red-600">Revoke</button>
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($showInvite)
        <x-modal title="Invite people" close="showInvite">
            <form wire:submit="invite" class="space-y-4">
                <div>
                    <label class="label">Email addresses</label>
                    <textarea wire:model="emails" rows="3" class="input" placeholder="ana@company.com, ben@company.com" autofocus></textarea>
                    @error('emails')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Role</label>
                    <select aria-label="Role" wire:model.live="role" class="input">
                        <option value="member">Member</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                    <p class="mt-1 text-xs text-ink-500">{{ \App\Models\Workspace::ROLE_DESCRIPTIONS[$role] ?? '' }}</p>
                </div>
                @if (in_array($role, ['member', 'manager'], true) && $allTeams->isNotEmpty())
                    <div>
                        <label class="label">Teams <span class="font-normal normal-case text-ink-500">(optional)</span></label>
                        <div class="space-y-1.5 rounded-md border border-gray-200 p-3">
                            @foreach ($allTeams as $t)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="inviteTeams" value="{{ $t->id }}" class="rounded border-gray-300 text-coral-500 focus:ring-coral-400">
                                    {{ $t->name }} <span class="text-xs text-ink-500">· {{ $t->projects_count }} {{ \Illuminate\Support\Str::plural('project', $t->projects_count) }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-ink-500">Team projects are only visible to that team's members. Leave all unticked to give access to workspace-wide projects only.</p>
                    </div>
                @endif
                @if ($sentLinks)
                    <div class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-800">
                        <p class="font-medium">Invitations sent. Share links directly if email isn't set up yet:</p>
                        <ul class="mt-2 space-y-1 break-all text-xs">
                            @foreach ($sentLinks as $email => $url)<li><strong>{{ $email }}</strong>: {{ $url }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="$set('showInvite', false)" class="btn-secondary btn">Done</button>
                    <button class="btn-primary btn"><x-icon name="mail" /> Send invites</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
