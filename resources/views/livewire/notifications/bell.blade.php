{{-- Open/close is pure Alpine (instant, animated); Livewire only fills the list. --}}
<div class="relative" wire:poll.30s.visible
     x-data="{ open: false, seen: false,
               show() { this.open = true; this.seen = true; $wire.opened() },
               hide() { this.open = false } }"
     x-on:keydown.escape.window="open && hide()">
    <button type="button" x-on:click="open ? hide() : show()"
            x-on:mouseenter.once="$wire.prefetch()" x-on:focus.once="$wire.prefetch()" x-on:touchstart.passive.once="$wire.prefetch()"
            class="btn-ghost btn relative px-1.5" :class="open && 'bg-gray-100'"
            aria-label="Notifications{{ $count ? ', '.$count.' new' : '' }}" aria-haspopup="true" :aria-expanded="open.toString()">
        <x-icon name="bell" class="h-5 w-5" />
        @if ($count)
            <span x-show="!open" x-transition.opacity.duration.150ms
                  class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent-600 px-1 text-[10px] font-semibold leading-none text-white">{{ $count >= 99 ? '99+' : $count }}</span>
        @endif
    </button>

    <div x-cloak x-show="open" x-on:click.outside="hide()" role="dialog" aria-label="Notifications"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
         class="fixed inset-x-2 top-14 z-50 origin-top-right overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-black/5 will-change-transform sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-[26rem]">
        <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
            <h2 class="text-sm font-semibold">Notifications</h2>
            <a href="{{ $allUrl }}" wire:navigate x-on:click="hide()" class="text-xs font-medium text-accent-700 hover:underline">View all</a>
        </div>
        <ul class="max-h-[min(70vh,32rem)] divide-y divide-gray-100 overflow-y-auto overscroll-contain" aria-busy="{{ $loaded ? 'false' : 'true' }}">
            @if (! $loaded)
                {{-- Placeholder rows while the list loads (usually already loaded on hover). --}}
                @for ($i = 0; $i < 4; $i++)
                    <li class="flex gap-3 px-4 py-3" aria-hidden="true">
                        <span class="h-8 w-8 shrink-0 animate-pulse rounded-full bg-gray-100"></span>
                        <span class="flex-1 space-y-2 pt-1">
                            <span class="block h-3 animate-pulse rounded bg-gray-100" style="width: {{ [88, 72, 80, 64][$i] }}%"></span>
                            <span class="block h-2.5 w-2/5 animate-pulse rounded bg-gray-100"></span>
                        </span>
                    </li>
                @endfor
            @else
                @forelse ($items as $a)
                    @include('livewire.notifications.item', ['a' => $a, 'feed' => $feed, 'isNew' => $since && $a->created_at->greaterThan($since), 'compact' => true, 'fadeIn' => true])
                @empty
                    <li class="tf-fade-in px-4 py-10 text-center text-sm text-ink-500">You’re all caught up. Changes your teammates make in your projects will show up here.</li>
                @endforelse
            @endif
        </ul>
    </div>
</div>
