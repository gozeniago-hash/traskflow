{{-- Global image viewer. Opened by clicking anything with data-lightbox (see resources/js/app.js). --}}
<div x-data="tfLightbox" x-cloak x-show="open" x-transition.opacity
     x-on:lightbox.window="show($event.detail)" x-on:keydown.window.capture="key($event)"
     class="fixed inset-0 z-[80] flex flex-col bg-ink-950/95 text-white" role="dialog" aria-modal="true" aria-label="Image viewer">
    <div class="flex items-center gap-3 px-4 py-3">
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium" x-text="current.name"></p>
            <p class="truncate text-xs text-ink-300"><span x-show="images.length > 1" x-text="(index + 1) + ' of ' + images.length"></span><span x-show="current.meta" x-text="(images.length > 1 ? ' · ' : '') + current.meta"></span></p>
        </div>
        <button type="button" x-on:click="zoomed = !zoomed" class="rounded-md px-2.5 py-1.5 text-sm text-ink-300 hover:bg-white/10 hover:text-white" x-text="zoomed ? 'Fit to screen' : 'Actual size'"></button>
        <a :href="current.src" target="_blank" rel="noopener" class="rounded-md p-1.5 text-ink-300 hover:bg-white/10 hover:text-white" title="Open in new tab"><x-icon name="arrow-up-right" class="h-5 w-5" /></a>
        <a :href="current.download" class="rounded-md p-1.5 text-ink-300 hover:bg-white/10 hover:text-white" title="Download"><x-icon name="download" class="h-5 w-5" /></a>
        <button type="button" x-on:click="close()" class="rounded-md p-1.5 text-ink-300 hover:bg-white/10 hover:text-white" title="Close (Esc)"><x-icon name="x" class="h-6 w-6" /></button>
    </div>

    <div class="relative flex min-h-0 flex-1 items-center justify-center" :class="zoomed ? 'overflow-auto' : 'overflow-hidden'" x-on:click.self="close()">
        <div x-show="loading" class="absolute inset-0 flex items-center justify-center text-sm text-ink-300">Loading…</div>
        <img :src="current.src" :alt="current.name" x-on:load="loading = false" x-on:error="loading = false"
             x-on:click="zoomed = !zoomed"
             :class="zoomed ? 'max-w-none cursor-zoom-out' : 'max-h-full max-w-full cursor-zoom-in object-contain'"
             class="select-none p-4 transition-opacity" :style="loading ? 'opacity:0' : 'opacity:1'">

        <template x-if="images.length > 1">
            <div>
                <button type="button" x-on:click.stop="go(-1)" class="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 hover:bg-black/70" title="Previous (←)"><x-icon name="chevron-left" class="h-6 w-6" /></button>
                <button type="button" x-on:click.stop="go(1)" class="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 hover:bg-black/70" title="Next (→)"><x-icon name="chevron-right" class="h-6 w-6" /></button>
            </div>
        </template>
    </div>

    <div x-show="images.length > 1" class="flex justify-center gap-2 overflow-x-auto px-4 py-3">
        <template x-for="(img, i) in images" :key="img.src">
            <button type="button" x-on:click="index = i; zoomed = false; loading = true" :aria-label="'Show image ' + (i + 1)" class="h-14 w-14 shrink-0 overflow-hidden rounded-md ring-2 transition" :class="i === index ? 'ring-coral-400' : 'ring-transparent opacity-60 hover:opacity-100'">
                <img :src="img.src" alt="" class="h-full w-full object-cover" loading="lazy">
            </button>
        </template>
    </div>
</div>
