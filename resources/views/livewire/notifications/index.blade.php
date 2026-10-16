<div class="{{ auth()->user()->isClient() ? '' : 'p-6' }}">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-xl font-semibold">Notifications</h1>
        <p class="mt-1 text-sm text-ink-500">What’s been added, changed and deleted in your projects over the last {{ \App\Support\NotificationFeed::DAYS }} days.</p>

        <div class="mt-5 flex flex-wrap items-end gap-3">
            <div>
                <label class="label" for="nf-project">Project</label>
                <select id="nf-project" wire:model.live="project" class="input input-sm w-auto max-w-64">
                    <option value="">All projects</option>
                    @foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="nf-kind">Type</label>
                <select id="nf-kind" wire:model.live="kind" class="input input-sm w-auto">
                    <option value="">Everything</option>
                    @foreach ($kinds as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <label class="mb-1.5 flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" wire:model.live="mine" class="rounded border-gray-300 text-accent-600 focus:ring-accent-400"> Include my own changes
            </label>
        </div>

        @if ($groups->isEmpty())
            <div class="mt-8"><x-empty icon="bell" title="Nothing new" text="When people add, change or delete things in your projects, you’ll see it here." /></div>
        @else
            @foreach ($groups as $day => $items)
                <h2 class="mb-2 mt-6 text-xs font-semibold text-ink-500">{{ $day }}</h2>
                <ul class="card divide-y divide-gray-100 overflow-hidden">
                    @foreach ($items as $a)
                        @include('livewire.notifications.item', ['a' => $a, 'feed' => $feed, 'isNew' => $a->created_at->greaterThan($since)])
                    @endforeach
                </ul>
            @endforeach
            @if ($hasMore)<div class="mt-4 flex justify-center"><button wire:click="more" class="btn-secondary btn">Show more</button></div>@endif
        @endif
    </div>
</div>
