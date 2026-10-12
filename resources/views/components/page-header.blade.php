@props(['title', 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4']) }}>
    <div class="min-w-0">
        <h1 class="truncate text-xl font-semibold text-ink-900">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-0.5 text-sm text-ink-500">{{ $subtitle }}</p>@endif
    </div>
    @if (isset($actions))<div class="flex items-center gap-2">{{ $actions }}</div>@endif
</div>
