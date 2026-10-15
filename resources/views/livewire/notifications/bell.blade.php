<div class="relative" wire:poll.30s.visible x-data x-on:keydown.escape.window="$wire.open && $wire.toggle()">
    <button type="button" wire:click="toggle" class="btn-ghost btn relative px-1.5" aria-label="Notifications{{ $count ? ', '.$count.' new' : '' }}" aria-haspopup="true" aria-expanded="{{ $open ? 'true' : 'false' }}">
        <x-icon name="bell" class="h-5 w-5" />
        @if ($count)
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-coral-600 px-1 text-[10px] font-semibold leading-none text-white">{{ $count >= 99 ? '99+' : $count }}</span>
        @endif
    </button>

    @if ($open)
        <div class="fixed inset-x-2 top-14 z-50 overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-black/5 sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-[26rem]"
             x-on:click.outside="$wire.toggle()" role="dialog" aria-label="Notifications">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
                <h2 class="text-sm font-semibold">Notifications</h2>
                <a href="{{ $allUrl }}" wire:navigate class="text-xs font-medium text-coral-700 hover:underline">View all</a>
            </div>
            <ul class="max-h-[min(70vh,32rem)] divide-y divide-gray-100 overflow-y-auto">
                @forelse ($items as $a)
                    @include('livewire.notifications.item', ['a' => $a, 'feed' => $feed, 'isNew' => $since && $a->created_at->greaterThan($since), 'compact' => true])
                @empty
                    <li class="px-4 py-10 text-center text-sm text-ink-500">You’re all caught up. Changes your teammates make in your projects will show up here.</li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
