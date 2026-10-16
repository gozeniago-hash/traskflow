<div>
    <x-page-header title="Audit log" subtitle="Sign-ins, security changes and admin actions in this workspace. Entries can’t be edited or deleted.">
        <x-slot:actions>
            <button wire:click="export" class="btn-secondary btn"><x-icon name="download" /> Export CSV</button>
        </x-slot:actions>
    </x-page-header>

    <div class="p-6">
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="al-group">Type</label>
                <select id="al-group" wire:model.live="group" class="input input-sm w-auto">
                    <option value="">Everything</option>
                    @foreach ($groups as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="al-person">Person</label>
                <select id="al-person" wire:model.live="person" class="input input-sm w-auto">
                    <option value="">Anyone</option>
                    @foreach ($people as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="al-from">From</label>
                <input id="al-from" type="date" wire:model.live="from" class="input input-sm w-40">
            </div>
            <div>
                <label class="label" for="al-to">To</label>
                <input id="al-to" type="date" wire:model.live="to" class="input input-sm w-40">
            </div>
        </div>

        @if ($entries->isEmpty())
            <x-empty icon="clock" title="Nothing logged yet" text="Sign-ins, role changes, invitations and other admin actions will appear here." />
        @else
            <div class="card overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs text-ink-500">
                        <tr><th class="px-4 py-2.5 font-semibold">When</th><th class="px-4 py-2.5 font-semibold">Who</th><th class="px-4 py-2.5 font-semibold">What happened</th><th class="hidden px-4 py-2.5 font-semibold md:table-cell">IP address</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($entries as $e)
                            <tr wire:key="al-{{ $e->id }}" class="align-top">
                                <td class="whitespace-nowrap px-4 py-2.5 text-ink-700" title="{{ $e->created_at->timezone($tz)->toDayDateTimeString() }}">{{ $e->created_at->timezone($tz)->format('M j, g:i A') }}</td>
                                <td class="px-4 py-2.5">
                                    @if ($e->user)<span class="flex items-center gap-2"><x-avatar :user="$e->user" size="xs" />{{ $e->user->name }}</span>@else<span class="text-ink-500">Deleted user</span>@endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <span @class(['font-medium text-red-700' => str_contains($e->action, 'failed')])>{{ $e->description }}</span>
                                    <span class="ml-1 font-mono text-[11px] text-ink-500">{{ $e->action }}</span>
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono text-xs text-ink-500 md:table-cell">{{ $e->ip }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($hasMore)<div class="mt-4 flex justify-center"><button wire:click="more" class="btn-secondary btn">Show more</button></div>@endif
        @endif
    </div>
</div>
