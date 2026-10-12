{{-- Render inside @if($flag) in a Livewire view. `close` is the boolean property that hides it. --}}
@props(['title' => null, 'close', 'closeValue' => 'false', 'width' => 'max-w-lg'])
<div class="fixed inset-0 z-50 overflow-y-auto" x-data x-on:keydown.escape.window="$wire.set('{{ $close }}', {{ $closeValue }})">
    <div class="fixed inset-0 bg-ink-950/40" wire:click="$set('{{ $close }}', {{ $closeValue }})"></div>
    <div class="relative mx-auto mt-[8vh] mb-8 w-[calc(100%-2rem)] {{ $width }} rounded-xl bg-white shadow-2xl">
        @if ($title)
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <button type="button" class="btn-ghost btn px-1.5" wire:click="$set('{{ $close }}', {{ $closeValue }})" aria-label="Close"><x-icon name="x" /></button>
            </div>
        @endif
        <div class="p-5">{{ $slot }}</div>
    </div>
</div>
