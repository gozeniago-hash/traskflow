@props(['icon' => 'sparkles', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 px-6 py-12 text-center']) }}>
    <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-coral-50 text-coral-500"><x-icon :name="$icon" class="h-5 w-5" /></span>
    <p class="font-medium text-ink-900">{{ $title }}</p>
    @if ($text)<p class="mt-1 max-w-sm text-sm text-ink-500">{{ $text }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>
