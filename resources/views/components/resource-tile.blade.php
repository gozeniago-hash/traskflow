{{--
    One key resource (link or file). Images open in the image viewer, PDFs and links in a
    new tab, other files download. Extra controls (share, reorder, remove) go in the slot.
--}}
@props(['resource', 'group' => 'resources', 'showVisibility' => false])
@php
    $r = $resource;
    $a = $r->attachment;
    if ($a) {
        $href = $a->isImage() || $a->isPdf() ? $a->viewUrl() : $a->downloadUrl();
        $sub = strtoupper($a->extension()).' · '.$a->humanSize();
    } else {
        $href = $r->url;
        $sub = $r->service() ? $r->service().' · '.$r->domain() : $r->domain();
    }
@endphp
<div {{ $attributes->merge(['class' => 'group relative flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-2.5 transition hover:z-20 hover:border-gray-300 hover:shadow-sm focus-within:z-20']) }}>
    @if ($a && $a->isImage())
        <img src="{{ $a->viewUrl() }}" alt="" loading="lazy" class="h-10 w-10 shrink-0 rounded-md object-cover ring-1 ring-black/5">
    @elseif ($a)
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md text-[10px] font-semibold {{ $a->isPdf() ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-ink-500' }}">{{ \Illuminate\Support\Str::limit(strtoupper($a->extension()), 4, '') }}</span>
    @else
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-sky-50 text-sky-600"><x-icon name="link" class="h-5 w-5" /></span>
    @endif

    <a href="{{ $href }}"
       @if ($a && $a->isImage()) data-lightbox="{{ $group }}" data-src="{{ $a->viewUrl() }}" data-download="{{ $a->downloadUrl() }}" data-name="{{ $a->displayName() }}" data-meta="{{ $sub }}"
       @elseif (! $a || $a->isPdf()) target="_blank" rel="noopener noreferrer" @endif
       class="min-w-0 flex-1 after:absolute after:inset-0 after:content-['']" title="{{ $r->title }}">
        <span class="block truncate text-sm font-medium text-ink-900">{{ $r->title }}</span>
        <span class="block truncate text-xs text-ink-500">{{ $sub }}</span>
    </a>

    @if ($showVisibility && ! $r->client_visible)
        <span class="pill relative z-10 shrink-0 bg-gray-100 text-ink-500" title="Hidden from the client portal"><x-icon name="lock" class="h-3 w-3" /> Internal</span>
    @endif
    @if (trim($slot))
        <div class="relative z-10 flex shrink-0 items-center gap-0.5 text-ink-300 group-hover:text-ink-500">{{ $slot }}</div>
    @endif
</div>
