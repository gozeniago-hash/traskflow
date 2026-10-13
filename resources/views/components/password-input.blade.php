{{-- Password field with a show/hide toggle. --}}
@props(['name' => 'password', 'id' => null, 'autocomplete' => 'current-password', 'autofocus' => false])
<div x-data="{ show: false }" class="relative">
    <input id="{{ $id ?? $name }}" name="{{ $name }}" :type="show ? 'text' : 'password'" type="password" required autocomplete="{{ $autocomplete }}" @if ($autofocus) autofocus @endif
           {{ $attributes->merge(['class' => 'input pr-10']) }}>
    <button type="button" x-on:click="show = !show" class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-ink-500 hover:text-ink-900"
            :aria-label="show ? 'Hide password' : 'Show password'" :title="show ? 'Hide password' : 'Show password'">
        <x-icon name="eye" class="h-4 w-4" x-show="!show" />
        <x-icon name="eye-off" class="h-4 w-4" x-show="show" x-cloak />
    </button>
</div>
