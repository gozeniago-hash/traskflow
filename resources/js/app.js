import Sortable from 'sortablejs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import 'trix';
import 'trix/dist/trix.css';

window.Sortable = Sortable;

/**
 * Live updates (Laravel Reverb). Settings come from <meta name="realtime">, rendered by the server,
 * so the same build works in every environment. Livewire components subscribe through
 * their "echo-private:…" listeners and re-render when data changes elsewhere.
 */
const realtimeMeta = document.querySelector('meta[name="realtime"]');
if (realtimeMeta) {
    try {
        const cfg = JSON.parse(realtimeMeta.content);
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: cfg.key,
            wsHost: cfg.host,
            wsPort: cfg.port,
            wssPort: cfg.port,
            forceTLS: cfg.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        });

        // Small "Live" indicator in the top bar.
        const setState = (state) => document.querySelectorAll('[data-live-indicator]').forEach((el) => (el.dataset.state = state));
        const conn = window.Echo.connector.pusher.connection;
        conn.bind('state_change', ({ current }) => setState(current));
        document.addEventListener('livewire:navigated', () => setState(conn.state));
    } catch (e) {
        console.warn('Live updates unavailable', e);
    }
}

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
// Buttons marked data-modal-shell show a modal frame instantly (see x-modal / portal).
document.addEventListener('click', (e) => {
    const el = e.target.closest?.('[data-modal-shell]');
    if (el) window.dispatchEvent(new CustomEvent('tf-modal-shell', { detail: { title: el.dataset.peekTitle || '' } }));
}, true);

// Remember which task row/card was pressed (see tfTaskPanel).
document.addEventListener('pointerdown', (e) => {
    const el = e.target.closest?.('[data-peek-title]');
    if (el) window.tfLastPeek = { title: el.dataset.peekTitle, at: Date.now() };
}, true);

// Tab rows that don't fit scroll sideways; fade the edge that has more, and keep the active tab in view.
window.tfTabScroll = function (el) {
    const update = () => {
        el.toggleAttribute('data-more-left', el.scrollLeft > 2);
        el.toggleAttribute('data-more-right', el.scrollLeft + el.clientWidth < el.scrollWidth - 2);
    };
    const active = el.querySelector('.tab-active, [aria-current="page"]');
    if (active && el.scrollWidth > el.clientWidth) el.scrollLeft = Math.max(0, active.offsetLeft - el.clientWidth / 2 + active.offsetWidth / 2);
    update();
    el.addEventListener('scroll', update, { passive: true });
    new ResizeObserver(update).observe(el);
};

// QR code for setting up two-step verification (library loaded only when needed).
window.tfQr = async function (canvas, text) {
    const QRCode = (await import('qrcode')).default;
    QRCode.toCanvas(canvas, text, { width: 176, margin: 1, color: { dark: '#15232a', light: '#ffffff' } });
};

// ---------------------------------------------------------------- dialogs + toasts

/**
 * window.tfDialog.confirm('Delete “Q4 plan”? It can’t be recovered.') → Promise<boolean>
 * window.tfDialog.prompt('Rename resource', 'Old name') → Promise<string|null>
 * Messages split at the first "?" into a title and a detail line.
 */
window.tfDialog = {
    ask(options) {
        return new Promise((resolve) => window.dispatchEvent(new CustomEvent('tf-dialog', { detail: { ...options, resolve } })));
    },
    confirm(message, options = {}) {
        return this.ask({ message, ...options });
    },
    prompt(message, value = '', options = {}) {
        return this.ask({ message, value, prompt: true, okLabel: 'Save', ...options });
    },
};

// Every wire:confirm opens the in-app dialog instead of the browser's confirm box.
document.addEventListener('click', (e) => {
    const el = e.target.closest && e.target.closest('[wire\\:confirm]');
    if (!el || !el.__livewire_confirm || el.__livewire_confirm.__tf) return;
    const message = (el.getAttribute('wire:confirm') || 'Are you sure?').replaceAll('\\n', '\n');
    const fn = (action) => {
        window.tfDialog.confirm(message, { okLabel: el.dataset.confirmLabel }).then((ok) => ok && action());
    };
    fn.__tf = true;
    el.__livewire_confirm = fn;
}, true);

document.addEventListener('alpine:init', () => {
    window.Alpine.data('tfDialogHost', () => ({
        visible: false, shown: false, title: '', detail: '', prompt: false, value: '', okLabel: 'OK', cancelLabel: 'Cancel', danger: false, resolve: null, returnFocus: null,
        open(d) {
            if (this.resolve) this.resolve(this.prompt ? null : false);
            const msg = String(d.message || 'Are you sure?');
            const cut = msg.indexOf('?');
            this.title = d.title || (cut > -1 && cut < msg.length - 1 ? msg.slice(0, cut + 1) : msg);
            this.detail = d.detail ?? (cut > -1 && cut < msg.length - 1 ? msg.slice(cut + 1).trim() : '');
            this.prompt = !!d.prompt;
            this.value = d.value || '';
            const verb = (msg.match(/^(Delete|Remove|Disconnect|Discard|Restore|Permanently delete)\b/i) || [])[1];
            this.danger = d.danger ?? /^(delete|remove|disconnect|discard|permanently)/i.test(verb || '');
            this.okLabel = d.okLabel || (verb ? verb.replace(/^permanently /i, '').replace(/^\w/, (c) => c.toUpperCase()) : 'OK');
            this.cancelLabel = d.cancelLabel || 'Cancel';
            this.resolve = d.resolve;
            this.returnFocus = document.activeElement;
            this.visible = true;
            this.$nextTick(() => {
                requestAnimationFrame(() => (this.shown = true));
                if (this.prompt && this.$refs.input) { this.$refs.input.focus(); this.$refs.input.select(); }
                else this.$refs.ok && this.$refs.ok.focus();
            });
        },
        close(result) {
            if (!this.visible) return;
            const resolve = this.resolve;
            this.resolve = null;
            this.shown = false;
            setTimeout(() => {
                this.visible = false;
                this.returnFocus && this.returnFocus.focus && this.returnFocus.focus();
            }, 120);
            if (resolve) resolve(this.prompt ? (result === null ? null : String(result).trim() || null) : result === true);
        },
    }));

    window.Alpine.data('tfToasts', (flash = null) => ({
        toasts: [],
        init() { if (flash) this.add({ message: flash }); },
        add(d) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, m: d.message, type: d.type || 'success', undo: d.undo || null, busy: false });
            setTimeout(() => this.remove(id), d.undo ? 8000 : 3500);
        },
        remove(id) { this.toasts = this.toasts.filter((t) => t.id !== id); },
        async undo(t) {
            t.busy = true;
            try {
                const res = await fetch(t.undo, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                });
                const data = await res.json().catch(() => ({}));
                this.remove(t.id);
                this.add({ message: data.message || (res.ok ? 'Restored.' : 'This can no longer be undone.'), type: res.ok ? 'success' : 'error' });
                if (res.ok && window.Livewire) window.Livewire.all().forEach((c) => c.$wire.$refresh());
            } catch (e) {
                t.busy = false;
            }
        },
    }));
});

document.addEventListener('alpine:init', () => {
    /**
     * x-modal: fades in when it appears and fades out before telling Livewire to close.
     * If an instant placeholder frame is already showing, it takes its place without a second fade.
     */
    window.Alpine.data('tfModal', (property, closedValue) => ({
        shown: false,
        init() {
            if (window.tfModalShell) {
                this.shown = true;
                window.dispatchEvent(new CustomEvent('tf-modal-shell-done'));
            } else {
                this.$nextTick(() => (this.shown = true));
            }
        },
        hide() {
            if (!this.shown) return;
            this.shown = false;
            // "closeSomething()" calls a server action (for locked properties); otherwise clear the property.
            setTimeout(() => property.endsWith('()') ? this.$wire.call(property.slice(0, -2)) : this.$wire.set(property, closedValue), 150);
        },
    }));

    /**
     * Task slide-over: opens instantly on click (before the server answers) and
     * slides out before Livewire clears the task, so both directions animate.
     */
    window.Alpine.data('tfTaskPanel', (initiallyOpen = false) => ({
        open: initiallyOpen,
        loading: false,
        peekTitle: '',
        fallback: null,
        init() {
            this.$watch(() => this.$wire.taskId, (id) => {
                if (id && !this.open) this.open = true;
                if (!id && this.open) this.open = false; // e.g. the task was deleted
            });
        },
        show() {
            // Title of the row/card that was clicked, so the panel shows it before the server answers.
            const peek = window.tfLastPeek;
            this.peekTitle = peek && Date.now() - peek.at < 1500 ? peek.title : '';
            this.loading = true;
            this.open = true;
            clearTimeout(this.fallback);
            this.fallback = setTimeout(() => (this.loading = false), 4000);
        },
        loaded() {
            clearTimeout(this.fallback);
            this.loading = false;
        },
        hide() {
            this.open = false;
            setTimeout(() => { if (!this.open) this.$wire.close(); }, 220);
        },
    }));

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
                if (!(this.dirty || this.uploading)) return;
                e.preventDefault();
                const url = e.detail && e.detail.url;
                window.tfDialog.confirm('Leave this doc? Your unsaved changes will be lost.', { okLabel: 'Leave', danger: true }).then((ok) => {
                    if (!ok || !url) return;
                    this.dirty = false;
                    this.uploading = false;
                    window.Livewire.navigate(String(url));
                });
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
