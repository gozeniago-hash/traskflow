{{-- Render inside @if($flag) in a Livewire view. `close` is the property that hides it; `closeValue` the value that does. Fades in and out. --}}
@props(['title' => null, 'close', 'closeValue' => 'false', 'width' => 'max-w-lg'])
<div class="fixed inset-0 z-50 overflow-y-auto" x-data="tfModal(@js($close), {{ $closeValue }})" x-on:keydown.escape.window="hide()">
    <div x-cloak x-show="shown"
         x-transition:enter="transition-opacity ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-ink-950/40" x-on:click="hide()"></div>
    <div x-cloak x-show="shown"
         x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="relative mx-auto mt-[8vh] mb-8 w-[calc(100%-2rem)] {{ $width }} rounded-xl bg-white shadow-2xl">
        @if ($title)
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <button type="button" class="btn-ghost btn px-1.5" x-on:click="hide()" aria-label="Close"><x-icon name="x" /></button>
            </div>
        @endif
        <div class="p-5">{{ $slot }}</div>
    </div>
</div>
