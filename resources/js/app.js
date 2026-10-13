import Sortable from 'sortablejs';
import 'trix';
import 'trix/dist/trix.css';

window.Sortable = Sortable;

/**
 * Drag-and-drop helper shared by the List and Board views.
 * Usage (Alpine): x-data x-init="taskflowSortable($el, $wire, sectionId)"
 *
 * Sortable moves DOM nodes itself; Livewire also re-renders the list. To keep
 * them from fighting, we read the new order, put the node back where it was,
 * and let the Livewire re-render apply the move.
 */
window.taskflowSortable = function (el, wire, method = 'reorder') {
    return Sortable.create(el, {
        group: el.dataset.group || 'tasks',
        animation: 150,
        draggable: '[data-task-id]',
        handle: el.dataset.handle || null,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd(e) {
            const to = e.to;
            const ids = [...to.querySelectorAll(':scope > [data-task-id]')].map((n) => Number(n.dataset.taskId));
            const sectionId = to.dataset.sectionId ? Number(to.dataset.sectionId) : null;

            // Revert the DOM change so Livewire's morph stays in sync.
            const sameList = e.from === e.to;
            const children = [...e.from.querySelectorAll(':scope > [data-task-id]')];
            let ref;
            if (sameList && e.newIndex < e.oldIndex) {
                ref = children[e.oldIndex + 1] ?? null;
            } else {
                ref = children[e.oldIndex] ?? null;
            }
            if (sameList && e.newIndex === e.oldIndex) return;
            e.from.insertBefore(e.item, ref);

            wire.call(method, sectionId, ids);
        },
    });
};

/**
 * Live timer display. `running` ticks every second from `base` seconds;
 * otherwise it just shows `base`. Server re-renders replace the element (via wire:key),
 * so the starting value is always fresh.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tfTimer', (running = false, base = 0) => ({
        seconds: base,
        handle: null,
        init() {
            if (!running) return;
            const startedAt = Date.now() - base * 1000;
            this.handle = setInterval(() => {
                this.seconds = Math.floor((Date.now() - startedAt) / 1000);
            }, 1000);
        },
        destroy() {
            clearInterval(this.handle);
        },
        get clock() {
            const s = this.seconds;
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            return `${h}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
        },
    }));
});

/**
 * Image viewer. Any element with data-lightbox="<group>" and data-src opens the viewer
 * on click; every image in the same group on the page becomes part of the gallery.
 */
document.addEventListener(
    'click',
    (e) => {
        const el = e.target.closest('[data-lightbox]');
        if (!el || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();

        const group = el.dataset.lightbox;
        const nodes = [...document.querySelectorAll('[data-lightbox]')].filter((n) => n.dataset.lightbox === group);
        const seen = new Set();
        const images = [];
        nodes.forEach((n) => {
            if (seen.has(n.dataset.src)) return;
            seen.add(n.dataset.src);
            images.push({ src: n.dataset.src, name: n.dataset.name || '', download: n.dataset.download || n.dataset.src, meta: n.dataset.meta || '' });
        });
        const index = Math.max(0, images.findIndex((i) => i.src === el.dataset.src));
        window.dispatchEvent(new CustomEvent('lightbox', { detail: { images, index } }));
    },
    true,
);

document.addEventListener('alpine:init', () => {
    window.Alpine.data('tfLightbox', () => ({
        open: false,
        images: [],
        index: 0,
        zoomed: false,
        loading: false,
        get current() {
            return this.images[this.index] || {};
        },
        show(detail) {
            this.images = detail.images || [];
            this.index = detail.index || 0;
            this.zoomed = false;
            this.loading = true;
            this.open = this.images.length > 0;
            document.documentElement.classList.add('overflow-hidden');
        },
        close() {
            this.open = false;
            document.documentElement.classList.remove('overflow-hidden');
        },
        go(step) {
            if (this.images.length < 2) return;
            this.index = (this.index + step + this.images.length) % this.images.length;
            this.zoomed = false;
            this.loading = true;
        },
        key(e) {
            if (!this.open) return;
            if (e.key === 'Escape') { e.stopPropagation(); this.close(); }
            if (e.key === 'ArrowRight') this.go(1);
            if (e.key === 'ArrowLeft') this.go(-1);
        },
    }));
});


/**
 * Project doc editor (Trix). Autosaves 1.5s after typing stops, uploads images/files dropped
 * or pasted into the doc, and warns before overwriting someone else's newer changes.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('tfDocEditor', (savedAt = '') => ({
        status: savedAt ? 'Saved ' + savedAt : '',
        conflict: null,
        dirty: false,
        uploading: 0,
        timer: null,
        init() {
            this.onChange = () => {
                this.dirty = true;
                this.status = 'Editing…';
                this.schedule();
            };
            this.$refs.trix.addEventListener('trix-change', this.onChange);
            this.$refs.title.addEventListener('input', this.onChange);
            this.$refs.trix.addEventListener('trix-attachment-add', (e) => this.upload(e.attachment));
            this.beforeUnload = (e) => {
                if (this.dirty || this.uploading) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            };
            window.addEventListener('beforeunload', this.beforeUnload);
            // wire:navigate page changes don't fire beforeunload.
            this.onNavigate = (e) => {
                if ((this.dirty || this.uploading) && !confirm('You have unsaved changes in this doc. Leave anyway?')) e.preventDefault();
            };
            document.addEventListener('livewire:navigate', this.onNavigate);
            this.$nextTick(() => this.$refs.trix.focus());
        },
        destroy() {
            clearTimeout(this.timer);
            window.removeEventListener('beforeunload', this.beforeUnload);
            document.removeEventListener('livewire:navigate', this.onNavigate);
        },
        schedule() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.save(), 1500);
        },
        async save(force = false) {
            clearTimeout(this.timer);
            if (this.uploading > 0) {
                this.schedule();
                return false;
            }
            this.status = 'Saving…';
            try {
                const res = await this.$wire.autosave(this.$refs.input.value, this.$refs.title.value, force);
                if (res && res.conflict) {
                    this.conflict = res.by;
                    this.status = 'Not saved';
                    return false;
                }
                this.conflict = null;
                this.dirty = false;
                this.status = 'Saved ' + (res?.savedAt || '');
                return true;
            } catch (err) {
                this.status = 'Could not save — check your connection';
                this.schedule();
                return false;
            }
        },
        async done() {
            if (this.dirty || this.uploading) {
                const ok = await this.save();
                if (!ok) return;
            }
            this.dirty = false;
            await this.$wire.finishEdit();
        },
        upload(attachment) {
            if (!attachment.file) return;
            this.uploading++;
            this.status = 'Uploading…';
            this.$wire.upload(
                'docUpload',
                attachment.file,
                async () => {
                    try {
                        const r = await this.$wire.storeDocUpload();
                        attachment.setAttributes({ url: r.url, href: r.href });
                    } catch (e) {
                        attachment.remove();
                    }
                    this.uploading--;
                    this.onChange();
                },
                () => {
                    attachment.remove();
                    this.uploading--;
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Upload failed. Files must be under the size limit.', type: 'error' } }));
                },
                (e) => attachment.setUploadProgress(e.detail.progress),
            );
        },
    }));
});

/** Make images inside rendered docs open in the image viewer. */
window.tfDocImages = function (root, group) {
    root.querySelectorAll('img').forEach((img) => {
        img.dataset.lightbox = group;
        img.dataset.src = img.getAttribute('src');
        img.dataset.download = img.getAttribute('src').replace('?inline=1', '');
        img.dataset.name = img.getAttribute('alt') || img.closest('figure')?.querySelector('figcaption')?.textContent?.trim() || 'Image';
        img.classList.add('cursor-zoom-in');
    });
};
