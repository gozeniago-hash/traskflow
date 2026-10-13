{{--
    One attachment. Images show a thumbnail and open in the image viewer;
    PDFs open in a new tab; other files download.
    Put extra controls (e.g. delete) in the slot.
--}}
@props(['attachment', 'group' => 'files', 'size' => 'md'])
@php
    $a = $attachment;
    $meta = trim($a->humanSize().($a->user ? ' · '.$a->user->name : '').' · '.$a->created_at->format('M j, Y'));
@endphp
@if ($a->isImage())
    <div {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-md border border-gray-200 bg-gray-50']) }}>
        <a href="{{ $a->viewUrl() }}" data-lightbox="{{ $group }}" data-src="{{ $a->viewUrl() }}" data-download="{{ $a->downloadUrl() }}" data-name="{{ $a->original_name }}" data-meta="{{ $meta }}"
           class="block {{ $size === 'sm' ? 'h-20' : 'h-28' }} w-full cursor-zoom-in" title="{{ $a->original_name }}">
            <img src="{{ $a->viewUrl() }}" alt="{{ $a->original_name }}" loading="lazy" class="h-full w-full object-cover transition group-hover:scale-[1.03]">
        </a>
        <div class="flex items-center gap-1 border-t border-gray-200 bg-white px-2 py-1">
            <span class="min-w-0 flex-1 truncate text-xs text-ink-700">{{ $a->original_name }}</span>
            {{ $slot }}
        </div>
    </div>
@else
    <div {{ $attributes->merge(['class' => 'group flex items-center gap-2 rounded-md border border-gray-200 bg-white p-2']) }}>
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded text-[10px] font-bold {{ $a->isPdf() ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-ink-500' }}">{{ \Illuminate\Support\Str::limit($a->extension(), 4, '') }}</span>
        <a href="{{ $a->isPdf() ? $a->viewUrl() : $a->downloadUrl() }}" @if ($a->isPdf()) target="_blank" rel="noopener" @endif class="min-w-0 flex-1">
            <span class="block truncate text-sm hover:underline">{{ $a->original_name }}</span>
            <span class="text-xs text-ink-500">{{ $a->humanSize() }}{{ $a->user ? ' · '.$a->user->firstName() : '' }}</span>
        </a>
        {{ $slot }}
    </div>
@endif
