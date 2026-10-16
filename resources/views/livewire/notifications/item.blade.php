@php($href = $feed->link($a))
<li wire:key="n-{{ $a->id }}" class="{{ $isNew ? 'bg-accent-50/50' : '' }} {{ ($fadeIn ?? false) ? 'tf-fade-in' : '' }}">
    <{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" wire:navigate @endif class="flex gap-3 px-4 py-3 {{ $href ? 'hover:bg-gray-50' : '' }}">
        <span class="relative shrink-0">
            <x-avatar :user="$a->user" size="md" />
            <span class="absolute -bottom-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-white text-ink-700 ring-1 ring-gray-200"><x-icon :name="$feed->icon($a)" class="h-2.5 w-2.5" /></span>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm text-ink-900 {{ $compact ?? false ? 'line-clamp-2' : '' }}"><span class="font-semibold">{{ $a->user?->name ?? 'Someone' }}</span> {{ $feed->headline($a) }}</span>
            <span class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-500">
                @if ($a->project)<span class="h-2 w-2 shrink-0 rounded-[3px]" style="background-color: {{ $a->project->color }}" aria-hidden="true"></span><span class="truncate">{{ $a->project->name }}</span><span aria-hidden="true">·</span>@endif
                <time datetime="{{ $a->created_at->toIso8601String() }}" title="{{ $a->created_at->timezone(config('taskflow.timezone'))->toDayDateTimeString() }}" class="shrink-0">{{ $a->created_at->diffForHumans(short: true) }}</time>
            </span>
        </span>
        @if ($isNew)<span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-accent-600" aria-label="New"></span>@endif
    </{{ $href ? 'a' : 'div' }}>
</li>
