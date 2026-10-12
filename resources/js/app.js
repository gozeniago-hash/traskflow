import Sortable from 'sortablejs';

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
